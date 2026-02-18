<?php
declare(strict_types=1);

class Game
{
    private const MULTIPLAYER_QUEUE_TIMEOUT_SECONDS = 20;
    private const MULTIPLAYER_ACTIVE_TIMEOUT_SECONDS = 1800;

    /**
     * Busca una partida activa del usuario o lo empareja con otra en espera.
     */
    public static function findOrCreateMultiplayer(int $userId): array
    {
        $pdo = db();
        self::cancelStaleMultiplayerWaiting($pdo);
        self::cancelStaleMultiplayerInProgress($pdo);

        $existingStmt = $pdo->prepare(
            "SELECT id, estado, jugador1_id, jugador2_id
             FROM partidas
             WHERE tipo = 'multijugador'
               AND estado IN ('esperando', 'en_curso')
               AND (jugador1_id = :user_id_1 OR jugador2_id = :user_id_2)
             ORDER BY CASE estado WHEN 'en_curso' THEN 0 ELSE 1 END, id DESC
             LIMIT 1"
        );
        $existingStmt->execute([
            'user_id_1' => $userId,
            'user_id_2' => $userId,
        ]);
        $existing = $existingStmt->fetch();

        if ($existing) {
            $existingId = (int) $existing['id'];
            $waiting = (string) $existing['estado'] === 'esperando';

            if ($waiting && (int) $existing['jugador1_id'] === $userId && $existing['jugador2_id'] === null) {
                self::touchMultiplayerWaitingGame($pdo, $existingId, $userId);
                self::cancelDuplicateWaitingMultiplayer($pdo, $userId, $existingId);
            } elseif (!$waiting) {
                self::cancelDuplicateWaitingMultiplayer($pdo, $userId);
            }

            return [
                'waiting' => $waiting,
                'game_id' => $existingId,
            ];
        }

        $pdo->beginTransaction();

        try {
            $waitingStmt = $pdo->prepare(
                "SELECT id, jugador1_id
                 FROM partidas
                 WHERE tipo = 'multijugador'
                   AND estado = 'esperando'
                   AND jugador2_id IS NULL
                   AND jugador1_id <> :user_id
                   AND TIMESTAMPDIFF(SECOND, fecha_actualizacion, NOW()) <= :timeout_seconds
                  ORDER BY fecha_creacion ASC
                  LIMIT 1
                  FOR UPDATE"
            );
            $waitingStmt->execute([
                'user_id' => $userId,
                'timeout_seconds' => self::MULTIPLAYER_QUEUE_TIMEOUT_SECONDS,
            ]);
            $waitingGame = $waitingStmt->fetch();

            // Si existe un jugador esperando, se completa la sala y se arranca la partida.
            if ($waitingGame) {
                $player1Id = (int) $waitingGame['jugador1_id'];
                $turn = random_int(0, 1) === 0 ? $player1Id : $userId;

                $updateStmt = $pdo->prepare(
                    "UPDATE partidas
                     SET jugador2_id = :player2,
                         estado = 'en_curso',
                         turno_actual = :turno
                     WHERE id = :id"
                );
                $updateStmt->execute([
                    'player2' => $userId,
                    'turno' => $turn,
                    'id' => (int) $waitingGame['id'],
                ]);

                $pdo->commit();

                return [
                    'waiting' => false,
                    'game_id' => (int) $waitingGame['id'],
                ];
            }

            // Si nadie espera, se crea una partida nueva en estado "esperando".
            $word = self::getRandomWord($pdo);

            $createStmt = $pdo->prepare(
                "INSERT INTO partidas
                    (tipo, estado, palabra, turno_actual, jugador1_id)
                 VALUES
                    ('multijugador', 'esperando', :palabra, :turno, :jugador1)"
            );
            $createStmt->execute([
                'palabra' => $word,
                'turno' => $userId,
                'jugador1' => $userId,
            ]);

            $gameId = (int) $pdo->lastInsertId();

            $pdo->commit();

            return [
                'waiting' => true,
                'game_id' => $gameId,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function cancelWaitingMultiplayer(int $userId, ?int $gameId = null): bool
    {
        $sql = "UPDATE partidas
                SET estado = 'cancelada',
                    turno_actual = NULL
                WHERE tipo = 'multijugador'
                  AND estado = 'esperando'
                  AND jugador2_id IS NULL
                  AND jugador1_id = :user_id";

        $params = [
            'user_id' => $userId,
        ];

        if ($gameId !== null && $gameId > 0) {
            $sql .= ' AND id = :game_id';
            $params['game_id'] = $gameId;
        }

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public static function getActiveMultiplayerGameIdForUser(int $userId): ?int
    {
        $stmt = db()->prepare(
            "SELECT id
             FROM partidas
             WHERE tipo = 'multijugador'
               AND estado = 'en_curso'
               AND (jugador1_id = :user_id_1 OR jugador2_id = :user_id_2)
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([
            'user_id_1' => $userId,
            'user_id_2' => $userId,
        ]);

        $gameId = $stmt->fetchColumn();

        return $gameId !== false ? (int) $gameId : null;
    }

    public static function getMultiplayerState(int $gameId, int $userId): ?array
    {
        $stmt = db()->prepare(
            "SELECT p.*,
                    u1.username AS jugador1_username,
                    COALESCE(u2.username, 'Esperando...') AS jugador2_username,
                    uw.username AS ganador_username
             FROM partidas p
             INNER JOIN usuarios u1 ON u1.id = p.jugador1_id
             LEFT JOIN usuarios u2 ON u2.id = p.jugador2_id
             LEFT JOIN usuarios uw ON uw.id = p.ganador_id
             WHERE p.id = :game_id
               AND p.tipo = 'multijugador'
               AND (p.jugador1_id = :user_id_1 OR p.jugador2_id = :user_id_2)
             LIMIT 1"
        );
        $stmt->execute([
            'game_id' => $gameId,
            'user_id_1' => $userId,
            'user_id_2' => $userId,
        ]);

        $game = $stmt->fetch();

        if (!$game) {
            return null;
        }

        $correct = self::explodeLetters((string) $game['letras_correctas']);
        $incorrect = self::explodeLetters((string) $game['letras_incorrectas']);
        $status = (string) $game['estado'];
        $canPlay = $status === 'en_curso' && (int) $game['turno_actual'] === $userId;

        $resultText = '';
        $infoText = '';

        if ($status === 'finalizada') {
            $winnerId = $game['ganador_id'] !== null ? (int) $game['ganador_id'] : null;

            if ($winnerId === $userId) {
                $resultText = 'Ganaste la partida. Recibiste +30 trofeos.';
            } elseif ($winnerId !== null) {
                $resultText = 'Perdiste la partida. Se actualizaron tus trofeos y estadisticas.';
            } else {
                $resultText = 'Partida finalizada.';
            }

            $infoText = 'Partida finalizada.';
        } elseif ($status === 'cancelada') {
            $resultText = 'La partida fue cancelada por inactividad.';
            $infoText = 'La partida fue cancelada.';
        } elseif ($status === 'esperando') {
            $infoText = 'Esperando que se una un rival.';
        } else {
            $infoText = $canPlay ? 'Es tu turno.' : 'Es el turno del rival.';
        }

        return [
            'id' => (int) $game['id'],
            'status' => $status,
            'masked_word' => self::maskWord((string) $game['palabra'], $correct, $status === 'finalizada'),
            'correct_letters' => $correct,
            'incorrect_letters' => $incorrect,
            'can_play' => $canPlay,
            'result_text' => $resultText,
            'info_text' => $infoText,
            'player1' => [
                'id' => (int) $game['jugador1_id'],
                'username' => (string) $game['jugador1_username'],
                'errors' => (int) $game['errores_jugador1'],
            ],
            'player2' => [
                'id' => (int) $game['jugador2_id'],
                'username' => (string) $game['jugador2_username'],
                'errors' => (int) $game['errores_jugador2'],
            ],
            'winner_id' => $game['ganador_id'] !== null ? (int) $game['ganador_id'] : null,
            'winner_username' => $game['ganador_username'] !== null ? (string) $game['ganador_username'] : null,
        ];
    }

    public static function playMultiplayerAction(int $gameId, int $userId, string $action, string $value): array
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                "SELECT *
                 FROM partidas
                 WHERE id = :game_id
                   AND tipo = 'multijugador'
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute(['game_id' => $gameId]);
            $game = $stmt->fetch();

            if (!$game) {
                $pdo->rollBack();

                return ['success' => false, 'message' => 'Partida no encontrada.'];
            }

            $player1 = (int) $game['jugador1_id'];
            $player2 = $game['jugador2_id'] !== null ? (int) $game['jugador2_id'] : null;

            if ($userId !== $player1 && $userId !== $player2) {
                $pdo->rollBack();

                return ['success' => false, 'message' => 'No perteneces a esta partida.'];
            }

            if ((string) $game['estado'] !== 'en_curso') {
                $pdo->rollBack();

                return ['success' => false, 'message' => 'La partida no esta en curso.'];
            }

            if ((int) $game['turno_actual'] !== $userId) {
                $pdo->rollBack();

                return ['success' => false, 'message' => 'No es tu turno.'];
            }

            $correct = self::explodeLetters((string) $game['letras_correctas']);
            $incorrect = self::explodeLetters((string) $game['letras_incorrectas']);
            $word = self::normalizeWord((string) $game['palabra']);
            $errors1 = (int) $game['errores_jugador1'];
            $errors2 = (int) $game['errores_jugador2'];

            if ($action === 'letter') {
                // Jugar una sola letra y bloquearla para ambos jugadores.
                $letter = self::normalizeLetter($value);

                if ($letter === null) {
                    $pdo->rollBack();

                    return ['success' => false, 'message' => 'La letra enviada no es valida.'];
                }

                if (in_array($letter, $correct, true) || in_array($letter, $incorrect, true)) {
                    $pdo->rollBack();

                    return ['success' => false, 'message' => 'Esa letra ya fue seleccionada.'];
                }

                if (strpos($word, $letter) !== false) {
                    $correct[] = $letter;
                } else {
                    $incorrect[] = $letter;

                    if ($userId === $player1) {
                        $errors1++;
                    } else {
                        $errors2++;
                    }
                }
            } elseif ($action === 'solve') {
                // Intento directo de resolver la palabra completa.
                $guess = self::normalizeWord($value);

                if ($guess === '') {
                    $pdo->rollBack();

                    return ['success' => false, 'message' => 'Debes enviar una palabra para resolver.'];
                }

                if ($guess === $word) {
                    $correct = self::uniqueWordLetters($word);
                } else {
                    if ($userId === $player1) {
                        $errors1++;
                    } else {
                        $errors2++;
                    }
                }
            } else {
                $pdo->rollBack();

                return ['success' => false, 'message' => 'Accion no soportada.'];
            }

            $winner = null;
            $loser = null;

            if (self::isWordResolved($word, $correct)) {
                $winner = $userId;
                $loser = $userId === $player1 ? $player2 : $player1;
            } elseif ($errors1 >= 6) {
                $winner = $player2;
                $loser = $player1;
            } elseif ($errors2 >= 6) {
                $winner = $player1;
                $loser = $player2;
            }

            if ($winner !== null && $loser !== null) {
                // Cierra partida y actualiza trofeos/estadisticas competitivas.
                self::finalizeMultiplayer($pdo, $gameId, $winner, $loser, $errors1, $errors2, $correct, $incorrect);
            } else {
                // Si no hay ganador, se pasa el turno al rival.
                $nextTurn = $userId === $player1 ? $player2 : $player1;

                $updateStmt = $pdo->prepare(
                    'UPDATE partidas
                     SET letras_correctas = :correctas,
                         letras_incorrectas = :incorrectas,
                         errores_jugador1 = :errores1,
                         errores_jugador2 = :errores2,
                         turno_actual = :turno
                     WHERE id = :id'
                );
                $updateStmt->execute([
                    'correctas' => self::implodeLetters($correct),
                    'incorrectas' => self::implodeLetters($incorrect),
                    'errores1' => $errors1,
                    'errores2' => $errors2,
                    'turno' => $nextTurn,
                    'id' => $gameId,
                ]);
            }

            $pdo->commit();

            return ['success' => true];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function abandonMultiplayerGame(int $gameId, int $userId): array
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $game = null;

            if ($gameId > 0) {
                $selectedStmt = $pdo->prepare(
                    "SELECT *
                     FROM partidas
                     WHERE id = :game_id
                       AND tipo = 'multijugador'
                       AND (jugador1_id = :user_id_1 OR jugador2_id = :user_id_2)
                     LIMIT 1
                     FOR UPDATE"
                );
                $selectedStmt->execute([
                    'game_id' => $gameId,
                    'user_id_1' => $userId,
                    'user_id_2' => $userId,
                ]);
                $selectedGame = $selectedStmt->fetch();

                if ($selectedGame && (string) $selectedGame['estado'] === 'en_curso') {
                    $game = $selectedGame;
                }
            }

            if (!$game) {
                $game = self::lockLatestActiveMultiplayerForUser($pdo, $userId);
            }

            if (!$game) {
                $pdo->rollBack();

                return ['success' => false, 'message' => 'No tienes una partida multijugador en curso para abandonar.'];
            }

            $player1 = (int) $game['jugador1_id'];
            $player2 = $game['jugador2_id'] !== null ? (int) $game['jugador2_id'] : null;

            if ($player2 === null) {
                $pdo->rollBack();

                return ['success' => false, 'message' => 'No hay rival asignado en esta partida.'];
            }

            $resolvedGameId = (int) $game['id'];
            $winnerId = $userId === $player1 ? $player2 : $player1;
            $errors1 = (int) $game['errores_jugador1'];
            $errors2 = (int) $game['errores_jugador2'];
            $correct = self::explodeLetters((string) $game['letras_correctas']);
            $incorrect = self::explodeLetters((string) $game['letras_incorrectas']);

            if ($userId === $player1) {
                $errors1 = max($errors1, 6);
            } else {
                $errors2 = max($errors2, 6);
            }

            self::finalizeMultiplayer($pdo, $resolvedGameId, $winnerId, $userId, $errors1, $errors2, $correct, $incorrect);

            $pdo->commit();

            return ['success' => true];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private static function lockLatestActiveMultiplayerForUser(PDO $pdo, int $userId): ?array
    {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM partidas
             WHERE tipo = 'multijugador'
               AND estado = 'en_curso'
               AND (jugador1_id = :user_id_1 OR jugador2_id = :user_id_2)
             ORDER BY id DESC
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([
            'user_id_1' => $userId,
            'user_id_2' => $userId,
        ]);

        $game = $stmt->fetch();

        return $game ?: null;
    }

    public static function createFriendlyRoom(int $creatorId): array
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            // Partida cooperativa: una palabra compartida para toda la sala.
            $word = self::getRandomWord($pdo);

            $gameStmt = $pdo->prepare(
                "INSERT INTO partidas
                    (tipo, estado, palabra, turno_actual, jugador1_id, creada_por_id)
                 VALUES
                    ('amistoso', 'esperando', :palabra, NULL, :jugador1, :creador)"
            );
            $gameStmt->execute([
                'palabra' => $word,
                'jugador1' => $creatorId,
                'creador' => $creatorId,
            ]);
            $gameId = (int) $pdo->lastInsertId();

            $code = self::generateUniqueRoomCode($pdo);

            $roomStmt = $pdo->prepare(
                "INSERT INTO salas
                    (codigo, creador_id, partida_id, estado)
                 VALUES
                    (:codigo, :creador_id, :partida_id, 'esperando')"
            );
            $roomStmt->execute([
                'codigo' => $code,
                'creador_id' => $creatorId,
                'partida_id' => $gameId,
            ]);

            $roomId = (int) $pdo->lastInsertId();

            $joinStmt = $pdo->prepare(
                'INSERT INTO sala_jugadores (sala_id, usuario_id) VALUES (:sala_id, :usuario_id)'
            );
            $joinStmt->execute([
                'sala_id' => $roomId,
                'usuario_id' => $creatorId,
            ]);

            $pdo->commit();

            return [
                'success' => true,
                'code' => $code,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function joinFriendlyRoom(string $roomCode, int $userId): array
    {
        $code = self::normalizeRoomCode($roomCode);

        if ($code === '') {
            return [
                'success' => false,
                'message' => 'El codigo de sala no es valido.',
            ];
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $roomStmt = $pdo->prepare(
                "SELECT s.id AS sala_id,
                        s.estado AS sala_estado,
                        s.creador_id,
                        p.id AS partida_id,
                        p.estado AS partida_estado,
                        p.jugador1_id,
                        p.jugador2_id
                 FROM salas s
                 INNER JOIN partidas p ON p.id = s.partida_id
                 WHERE s.codigo = :codigo
                 LIMIT 1
                 FOR UPDATE"
            );
            $roomStmt->execute(['codigo' => $code]);
            $room = $roomStmt->fetch();

            if (!$room) {
                $pdo->rollBack();

                return ['success' => false, 'message' => 'No existe una sala con ese codigo.'];
            }

            if ((string) $room['sala_estado'] === 'cerrada') {
                $pdo->rollBack();

                return ['success' => false, 'message' => 'La sala ya esta cerrada.'];
            }

            $memberStmt = $pdo->prepare(
                'SELECT id FROM sala_jugadores WHERE sala_id = :sala_id AND usuario_id = :usuario_id LIMIT 1'
            );
            $memberStmt->execute([
                'sala_id' => (int) $room['sala_id'],
                'usuario_id' => $userId,
            ]);

            $isAlreadyMember = (bool) $memberStmt->fetchColumn();

            $playersStmt = $pdo->prepare('SELECT usuario_id FROM sala_jugadores WHERE sala_id = :sala_id FOR UPDATE');
            $playersStmt->execute(['sala_id' => (int) $room['sala_id']]);
            $players = $playersStmt->fetchAll();
            $playersCount = count($players);

            if (!$isAlreadyMember && $playersCount >= 2) {
                $pdo->rollBack();

                return ['success' => false, 'message' => 'La sala ya esta completa.'];
            }

            if (!$isAlreadyMember) {
                $insertMemberStmt = $pdo->prepare(
                    'INSERT INTO sala_jugadores (sala_id, usuario_id) VALUES (:sala_id, :usuario_id)'
                );
                $insertMemberStmt->execute([
                    'sala_id' => (int) $room['sala_id'],
                    'usuario_id' => $userId,
                ]);

                if ((int) $room['jugador1_id'] !== $userId && $room['jugador2_id'] === null) {
                    $setSecondPlayerStmt = $pdo->prepare('UPDATE partidas SET jugador2_id = :jugador2 WHERE id = :id');
                    $setSecondPlayerStmt->execute([
                        'jugador2' => $userId,
                        'id' => (int) $room['partida_id'],
                    ]);
                }
            }

            $pdo->commit();

            return [
                'success' => true,
                'code' => $code,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function getFriendlyRoomState(string $roomCode, int $userId): ?array
    {
        $code = self::normalizeRoomCode($roomCode);

        if ($code === '') {
            return null;
        }

        $roomStmt = db()->prepare(
            "SELECT s.id AS sala_id,
                    s.codigo,
                    s.creador_id,
                    s.estado AS sala_estado,
                    p.estado AS partida_estado
             FROM salas s
             INNER JOIN partidas p ON p.id = s.partida_id
             INNER JOIN sala_jugadores sj ON sj.sala_id = s.id
             WHERE s.codigo = :codigo
               AND sj.usuario_id = :usuario_id
             LIMIT 1"
        );
        $roomStmt->execute([
            'codigo' => $code,
            'usuario_id' => $userId,
        ]);
        $room = $roomStmt->fetch();

        if (!$room) {
            return null;
        }

        $playersStmt = db()->prepare(
            "SELECT u.id, u.username
             FROM sala_jugadores sj
             INNER JOIN usuarios u ON u.id = sj.usuario_id
             WHERE sj.sala_id = :sala_id
             ORDER BY sj.fecha_union ASC"
        );
        $playersStmt->execute(['sala_id' => (int) $room['sala_id']]);

        return [
            'code' => (string) $room['codigo'],
            'is_creator' => (int) $room['creador_id'] === $userId,
            'started' => (string) $room['partida_estado'] === 'en_curso' || (string) $room['partida_estado'] === 'finalizada',
            'players' => $playersStmt->fetchAll(),
        ];
    }

    public static function startFriendlyRoom(string $roomCode, int $userId): array
    {
        $code = self::normalizeRoomCode($roomCode);

        if ($code === '') {
            return ['success' => false, 'message' => 'Codigo de sala invalido.'];
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $roomStmt = $pdo->prepare(
                "SELECT s.id AS sala_id,
                        s.creador_id,
                        p.id AS partida_id,
                        p.estado AS partida_estado
                 FROM salas s
                 INNER JOIN partidas p ON p.id = s.partida_id
                 WHERE s.codigo = :codigo
                 LIMIT 1
                 FOR UPDATE"
            );
            $roomStmt->execute(['codigo' => $code]);
            $room = $roomStmt->fetch();

            if (!$room) {
                $pdo->rollBack();

                return ['success' => false, 'message' => 'La sala no existe.'];
            }

            if ((int) $room['creador_id'] !== $userId) {
                $pdo->rollBack();

                return ['success' => false, 'message' => 'Solo el creador puede iniciar la partida.'];
            }

            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM sala_jugadores WHERE sala_id = :sala_id');
            $countStmt->execute(['sala_id' => (int) $room['sala_id']]);
            $playersCount = (int) $countStmt->fetchColumn();

            if ($playersCount < 2) {
                $pdo->rollBack();

                return ['success' => false, 'message' => 'Se necesitan 2 jugadores para iniciar.'];
            }

            if ((string) $room['partida_estado'] === 'en_curso') {
                $pdo->commit();

                return ['success' => true];
            }

            $startGameStmt = $pdo->prepare('UPDATE partidas SET estado = \'en_curso\' WHERE id = :id');
            $startGameStmt->execute(['id' => (int) $room['partida_id']]);

            $startRoomStmt = $pdo->prepare('UPDATE salas SET estado = \'en_juego\' WHERE id = :id');
            $startRoomStmt->execute(['id' => (int) $room['sala_id']]);

            $pdo->commit();

            return ['success' => true];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function getFriendlyGameState(string $roomCode, int $userId): ?array
    {
        $code = self::normalizeRoomCode($roomCode);

        if ($code === '') {
            return null;
        }

        $stmt = db()->prepare(
            "SELECT s.id AS sala_id,
                    p.*
             FROM salas s
             INNER JOIN partidas p ON p.id = s.partida_id
             INNER JOIN sala_jugadores sj ON sj.sala_id = s.id
             WHERE s.codigo = :codigo
               AND sj.usuario_id = :usuario_id
             LIMIT 1"
        );
        $stmt->execute([
            'codigo' => $code,
            'usuario_id' => $userId,
        ]);
        $game = $stmt->fetch();

        if (!$game) {
            return null;
        }

        $playersStmt = db()->prepare(
            "SELECT u.id, u.username
             FROM sala_jugadores sj
             INNER JOIN usuarios u ON u.id = sj.usuario_id
             WHERE sj.sala_id = :sala_id
             ORDER BY sj.fecha_union ASC"
        );
        $playersStmt->execute(['sala_id' => (int) $game['sala_id']]);
        $players = $playersStmt->fetchAll();

        $word = self::normalizeWord((string) $game['palabra']);
        $correct = self::explodeLetters((string) $game['letras_correctas']);
        $incorrect = self::explodeLetters((string) $game['letras_incorrectas']);
        $status = (string) $game['estado'];
        $sharedErrors = (int) $game['errores_jugador1'];

        $resultText = '';
        if ($status === 'finalizada') {
            if (self::isWordResolved($word, $correct)) {
                $resultText = 'Victoria cooperativa: resolvieron la palabra.';
            } else {
                $resultText = 'Derrota cooperativa: alcanzaron el maximo de errores.';
            }
        }

        return [
            'status' => $status,
            'players' => $players,
            'shared_errors' => $sharedErrors,
            'masked_word' => self::maskWord($word, $correct, $status === 'finalizada'),
            'correct_letters' => $correct,
            'incorrect_letters' => $incorrect,
            'can_play' => $status === 'en_curso',
            'result_text' => $resultText,
        ];
    }

    public static function playFriendlyAction(string $roomCode, int $userId, string $action, string $value): array
    {
        $code = self::normalizeRoomCode($roomCode);

        if ($code === '') {
            return ['success' => false, 'message' => 'Codigo de sala invalido.'];
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                "SELECT s.id AS sala_id,
                        p.id AS partida_id,
                        p.estado,
                        p.palabra,
                        p.letras_correctas,
                        p.letras_incorrectas,
                        p.errores_jugador1
                 FROM salas s
                 INNER JOIN partidas p ON p.id = s.partida_id
                 INNER JOIN sala_jugadores sj ON sj.sala_id = s.id
                 WHERE s.codigo = :codigo
                   AND sj.usuario_id = :usuario_id
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute([
                'codigo' => $code,
                'usuario_id' => $userId,
            ]);
            $game = $stmt->fetch();

            if (!$game) {
                $pdo->rollBack();

                return ['success' => false, 'message' => 'No tienes acceso a esta sala.'];
            }

            if ((string) $game['estado'] !== 'en_curso') {
                $pdo->rollBack();

                return ['success' => false, 'message' => 'La partida amistosa no esta en curso.'];
            }

            $word = self::normalizeWord((string) $game['palabra']);
            $correct = self::explodeLetters((string) $game['letras_correctas']);
            $incorrect = self::explodeLetters((string) $game['letras_incorrectas']);
            $sharedErrors = (int) $game['errores_jugador1'];

            if ($action === 'letter') {
                // En amistoso no hay turnos individuales: cualquier jugador puede intentar letras.
                $letter = self::normalizeLetter($value);

                if ($letter === null) {
                    $pdo->rollBack();

                    return ['success' => false, 'message' => 'La letra enviada no es valida.'];
                }

                if (in_array($letter, $correct, true) || in_array($letter, $incorrect, true)) {
                    $pdo->rollBack();

                    return ['success' => false, 'message' => 'Esa letra ya fue seleccionada.'];
                }

                if (strpos($word, $letter) !== false) {
                    $correct[] = $letter;
                } else {
                    $incorrect[] = $letter;
                    $sharedErrors++;
                }
            } elseif ($action === 'solve') {
                $guess = self::normalizeWord($value);

                if ($guess === '') {
                    $pdo->rollBack();

                    return ['success' => false, 'message' => 'Debes ingresar una palabra para resolver.'];
                }

                if ($guess === $word) {
                    $correct = self::uniqueWordLetters($word);
                } else {
                    $sharedErrors++;
                }
            } else {
                $pdo->rollBack();

                return ['success' => false, 'message' => 'Accion no soportada.'];
            }

            $status = 'en_curso';
            // La partida termina por palabra completa o por 6 errores compartidos.
            if (self::isWordResolved($word, $correct) || $sharedErrors >= 6) {
                $status = 'finalizada';
            }

            $updateStmt = $pdo->prepare(
                'UPDATE partidas
                 SET estado = :estado,
                     errores_jugador1 = :errores,
                     letras_correctas = :correctas,
                     letras_incorrectas = :incorrectas
                 WHERE id = :id'
            );
            $updateStmt->execute([
                'estado' => $status,
                'errores' => $sharedErrors,
                'correctas' => self::implodeLetters($correct),
                'incorrectas' => self::implodeLetters($incorrect),
                'id' => (int) $game['partida_id'],
            ]);

            if ($status === 'finalizada') {
                $closeRoomStmt = $pdo->prepare('UPDATE salas SET estado = \'cerrada\' WHERE id = :id');
                $closeRoomStmt->execute(['id' => (int) $game['sala_id']]);
            }

            $pdo->commit();

            return ['success' => true];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private static function cancelStaleMultiplayerWaiting(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            "UPDATE partidas
             SET estado = 'cancelada',
                 turno_actual = NULL
             WHERE tipo = 'multijugador'
               AND estado = 'esperando'
               AND jugador2_id IS NULL
               AND TIMESTAMPDIFF(SECOND, fecha_actualizacion, NOW()) > :timeout_seconds"
        );
        $stmt->execute([
            'timeout_seconds' => self::MULTIPLAYER_QUEUE_TIMEOUT_SECONDS,
        ]);
    }

    private static function cancelStaleMultiplayerInProgress(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            "UPDATE partidas
             SET estado = 'cancelada',
                 turno_actual = NULL
             WHERE tipo = 'multijugador'
               AND estado = 'en_curso'
               AND ganador_id IS NULL
               AND TIMESTAMPDIFF(SECOND, fecha_actualizacion, NOW()) > :timeout_seconds"
        );
        $stmt->execute([
            'timeout_seconds' => self::MULTIPLAYER_ACTIVE_TIMEOUT_SECONDS,
        ]);
    }

    private static function cancelDuplicateWaitingMultiplayer(PDO $pdo, int $userId, ?int $keepGameId = null): void
    {
        $sql = "UPDATE partidas
                SET estado = 'cancelada',
                    turno_actual = NULL
                WHERE tipo = 'multijugador'
                  AND estado = 'esperando'
                  AND jugador2_id IS NULL
                  AND jugador1_id = :user_id";

        $params = [
            'user_id' => $userId,
        ];

        if ($keepGameId !== null) {
            $sql .= ' AND id <> :keep_game_id';
            $params['keep_game_id'] = $keepGameId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    private static function touchMultiplayerWaitingGame(PDO $pdo, int $gameId, int $userId): void
    {
        $stmt = $pdo->prepare(
            "UPDATE partidas
             SET fecha_actualizacion = NOW()
             WHERE id = :id
               AND tipo = 'multijugador'
               AND estado = 'esperando'
               AND jugador1_id = :jugador1
               AND jugador2_id IS NULL"
        );
        $stmt->execute([
            'id' => $gameId,
            'jugador1' => $userId,
        ]);
    }

    private static function finalizeMultiplayer(PDO $pdo, int $gameId, int $winnerId, int $loserId, int $errors1, int $errors2, array $correct, array $incorrect): void
    {
        $updateGameStmt = $pdo->prepare(
            'UPDATE partidas
             SET estado = \'finalizada\',
                 ganador_id = :ganador,
                 turno_actual = NULL,
                 errores_jugador1 = :errores1,
                 errores_jugador2 = :errores2,
                 letras_correctas = :correctas,
                 letras_incorrectas = :incorrectas
             WHERE id = :id'
        );
        $updateGameStmt->execute([
            'ganador' => $winnerId,
            'errores1' => $errors1,
            'errores2' => $errors2,
            'correctas' => self::implodeLetters($correct),
            'incorrectas' => self::implodeLetters($incorrect),
            'id' => $gameId,
        ]);

        $winnerStatsStmt = $pdo->prepare(
            'UPDATE usuarios
             SET trofeos = trofeos + 30,
                 partidas_jugadas = partidas_jugadas + 1,
                 partidas_ganadas = partidas_ganadas + 1
             WHERE id = :id'
        );
        $winnerStatsStmt->execute(['id' => $winnerId]);

        // Penalizacion variable solicitada para el perdedor.
        $penalty = random_int(25, 28);

        $loserStatsStmt = $pdo->prepare(
            'UPDATE usuarios
             SET trofeos = GREATEST(0, trofeos - :penalizacion),
                 partidas_jugadas = partidas_jugadas + 1,
                 partidas_perdidas = partidas_perdidas + 1
             WHERE id = :id'
        );
        $loserStatsStmt->execute([
            'penalizacion' => $penalty,
            'id' => $loserId,
        ]);
    }

    private static function getRandomWord(PDO $pdo): string
    {
        $stmt = $pdo->query('SELECT palabra FROM palabras ORDER BY RAND() LIMIT 1');
        $word = $stmt->fetchColumn();

        if (is_string($word) && trim($word) !== '') {
            return self::normalizeWord($word);
        }

        $fallback = ['PROGRAMACION', 'SERVIDOR', 'JAVASCRIPT', 'CONTROLADOR', 'BASEDEDATOS'];

        return $fallback[random_int(0, count($fallback) - 1)];
    }

    private static function normalizeWord(string $value): string
    {
        $word = strtoupper(trim($value));

        return preg_replace('/\s+/', ' ', $word) ?? '';
    }

    private static function normalizeLetter(string $value): ?string
    {
        $letter = strtoupper(trim($value));

        if (!preg_match('/^[A-Z]$/', $letter)) {
            return null;
        }

        return $letter;
    }

    private static function explodeLetters(string $letters): array
    {
        if (trim($letters) === '') {
            return [];
        }

        $values = array_filter(array_map('trim', explode(',', $letters)), static fn ($letter) => $letter !== '');

        return array_values(array_unique($values));
    }

    private static function implodeLetters(array $letters): string
    {
        if ($letters === []) {
            return '';
        }

        return implode(',', array_values(array_unique($letters)));
    }

    private static function uniqueWordLetters(string $word): array
    {
        $letters = [];
        $chars = str_split($word);

        foreach ($chars as $char) {
            if (preg_match('/[A-Z]/', $char)) {
                $letters[] = $char;
            }
        }

        return array_values(array_unique($letters));
    }

    private static function isWordResolved(string $word, array $correctLetters): bool
    {
        $targets = self::uniqueWordLetters($word);

        foreach ($targets as $letter) {
            if (!in_array($letter, $correctLetters, true)) {
                return false;
            }
        }

        return true;
    }

    private static function maskWord(string $word, array $correctLetters, bool $reveal = false): string
    {
        $output = [];

        foreach (str_split($word) as $char) {
            if (!preg_match('/[A-Z]/', $char)) {
                $output[] = $char;
                continue;
            }

            if ($reveal || in_array($char, $correctLetters, true)) {
                $output[] = $char;
            } else {
                $output[] = '_';
            }
        }

        return implode(' ', $output);
    }

    private static function normalizeRoomCode(string $value): string
    {
        $code = strtoupper(trim($value));

        return preg_replace('/[^A-Z0-9]/', '', $code) ?? '';
    }

    private static function generateUniqueRoomCode(PDO $pdo): string
    {
        $attempts = 0;

        while ($attempts < 10) {
            $attempts++;
            $code = self::randomCode(6);

            $checkStmt = $pdo->prepare('SELECT id FROM salas WHERE codigo = :codigo LIMIT 1');
            $checkStmt->execute(['codigo' => $code]);

            if (!$checkStmt->fetchColumn()) {
                return $code;
            }
        }

        return self::randomCode(8);
    }

    private static function randomCode(int $length): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $code;
    }
}
