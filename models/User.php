<?php
declare(strict_types=1);

class User
{
    private static bool $friendshipsTableEnsured = false;
    private static bool $friendRequestsTableEnsured = false;

    public static function findByLogin(string $login): ?array
    {
        $sql = 'SELECT id, email, username, password, trofeos, partidas_jugadas, partidas_ganadas, partidas_perdidas
                FROM usuarios
                WHERE email = :email_login OR username = :username_login
                LIMIT 1';

        $stmt = db()->prepare($sql);
        $stmt->execute([
            'email_login' => $login,
            'username_login' => $login,
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = db()->prepare('SELECT id, email, username, trofeos, partidas_jugadas, partidas_ganadas, partidas_perdidas, fecha_registro
                               FROM usuarios
                               WHERE id = :id
                               LIMIT 1');
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function existsByEmail(string $email): bool
    {
        $stmt = db()->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        return (bool) $stmt->fetchColumn();
    }

    public static function existsByUsername(string $username): bool
    {
        $stmt = db()->prepare('SELECT id FROM usuarios WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);

        return (bool) $stmt->fetchColumn();
    }

    public static function create(string $email, string $username, string $passwordHash): bool
    {
        $sql = 'INSERT INTO usuarios (email, username, password)
                VALUES (:email, :username, :password)';

        $stmt = db()->prepare($sql);

        return $stmt->execute([
            'email' => $email,
            'username' => $username,
            'password' => $passwordHash,
        ]);
    }

    public static function getRanking(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        $sql = "SELECT id,
                       username,
                       trofeos,
                       partidas_jugadas,
                       partidas_ganadas,
                       CASE
                           WHEN partidas_jugadas = 0 THEN 0
                           ELSE ROUND((partidas_ganadas / partidas_jugadas) * 100, 2)
                       END AS winrate
                FROM usuarios
                ORDER BY trofeos DESC, partidas_ganadas DESC, username ASC
                LIMIT {$limit}";

        $stmt = db()->query($sql);

        return $stmt->fetchAll();
    }

    public static function getTopPosition(int $userId): ?int
    {
        $user = self::findById($userId);

        if (!$user) {
            return null;
        }

        $sql = 'SELECT COUNT(*) + 1 AS posicion
                FROM usuarios
                WHERE trofeos > :trofeos_mayor
                   OR (trofeos = :trofeos_igual AND id < :id)';

        $stmt = db()->prepare($sql);
        $stmt->execute([
            'trofeos_mayor' => (int) $user['trofeos'],
            'trofeos_igual' => (int) $user['trofeos'],
            'id' => $userId,
        ]);

        $position = (int) $stmt->fetchColumn();

        return $position <= 5 ? $position : null;
    }

    public static function sendFriendRequestByUsername(int $userId, string $username): array
    {
        self::ensureFriendshipsInfrastructure();

        $cleanUsername = trim($username);

        if ($cleanUsername === '') {
            return [
                'success' => false,
                'message' => 'Debes indicar un nombre de usuario.',
            ];
        }

        $targetStmt = db()->prepare('SELECT id, username FROM usuarios WHERE username = :username LIMIT 1');
        $targetStmt->execute([
            'username' => $cleanUsername,
        ]);
        $target = $targetStmt->fetch();

        if (!$target) {
            return [
                'success' => false,
                'message' => 'No existe un jugador con ese nombre de usuario.',
            ];
        }

        $targetId = (int) $target['id'];

        if ($targetId === $userId) {
            return [
                'success' => false,
                'message' => 'No puedes agregarte a ti mismo como amigo.',
            ];
        }

        if (self::areFriends($userId, $targetId)) {
            return [
                'success' => false,
                'message' => 'Ese jugador ya esta en tu lista de amigos.',
            ];
        }

        $pendingStmt = db()->prepare(
            'SELECT id, emisor_id, receptor_id
             FROM solicitudes_amistad
             WHERE (emisor_id = :emisor_a AND receptor_id = :receptor_a)
                OR (emisor_id = :emisor_b AND receptor_id = :receptor_b)
             LIMIT 1'
        );
        $pendingStmt->execute([
            'emisor_a' => $userId,
            'receptor_a' => $targetId,
            'emisor_b' => $targetId,
            'receptor_b' => $userId,
        ]);
        $pending = $pendingStmt->fetch();

        if ($pending) {
            if ((int) $pending['emisor_id'] === $userId) {
                return [
                    'success' => false,
                    'message' => 'Ya enviaste una solicitud a este jugador.',
                ];
            }

            return [
                'success' => false,
                'message' => 'Este jugador ya te envio una solicitud. Revisa solicitudes recibidas.',
            ];
        }

        $insertStmt = db()->prepare(
            'INSERT INTO solicitudes_amistad (emisor_id, receptor_id) VALUES (:emisor_id, :receptor_id)'
        );
        $insertStmt->execute([
            'emisor_id' => $userId,
            'receptor_id' => $targetId,
        ]);

        if ($insertStmt->rowCount() > 0) {
            return [
                'success' => true,
                'message' => 'Solicitud enviada a ' . (string) $target['username'] . '.',
            ];
        }

        return [
            'success' => false,
            'message' => 'No se pudo enviar la solicitud de amistad.',
        ];
    }

    public static function getIncomingFriendRequests(int $userId): array
    {
        self::ensureFriendshipsInfrastructure();

        $stmt = db()->prepare(
            "SELECT sa.id,
                    sa.emisor_id,
                    sa.fecha_creacion,
                    u.username,
                    u.trofeos
             FROM solicitudes_amistad sa
             INNER JOIN usuarios u ON u.id = sa.emisor_id
             WHERE sa.receptor_id = :user_id
             ORDER BY sa.fecha_creacion DESC"
        );
        $stmt->execute([
            'user_id' => $userId,
        ]);

        $requests = [];

        foreach ($stmt->fetchAll() as $row) {
            $requests[] = [
                'id' => (int) $row['id'],
                'from_user_id' => (int) $row['emisor_id'],
                'username' => (string) $row['username'],
                'trofeos' => (int) $row['trofeos'],
                'created_at' => (string) $row['fecha_creacion'],
            ];
        }

        return $requests;
    }

    public static function getOutgoingFriendRequests(int $userId): array
    {
        self::ensureFriendshipsInfrastructure();

        $stmt = db()->prepare(
            "SELECT sa.id,
                    sa.receptor_id,
                    sa.fecha_creacion,
                    u.username,
                    u.trofeos
             FROM solicitudes_amistad sa
             INNER JOIN usuarios u ON u.id = sa.receptor_id
             WHERE sa.emisor_id = :user_id
             ORDER BY sa.fecha_creacion DESC"
        );
        $stmt->execute([
            'user_id' => $userId,
        ]);

        $requests = [];

        foreach ($stmt->fetchAll() as $row) {
            $requests[] = [
                'id' => (int) $row['id'],
                'to_user_id' => (int) $row['receptor_id'],
                'username' => (string) $row['username'],
                'trofeos' => (int) $row['trofeos'],
                'created_at' => (string) $row['fecha_creacion'],
            ];
        }

        return $requests;
    }

    public static function resolveFriendRequest(int $userId, int $requestId, string $action): array
    {
        self::ensureFriendshipsInfrastructure();

        $decision = strtolower(trim($action));

        if ($requestId <= 0 || ($decision !== 'accept' && $decision !== 'reject')) {
            return [
                'success' => false,
                'message' => 'Solicitud invalida.',
            ];
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $requestStmt = $pdo->prepare(
                'SELECT id, emisor_id, receptor_id
                 FROM solicitudes_amistad
                 WHERE id = :id
                   AND receptor_id = :receptor_id
                 LIMIT 1
                 FOR UPDATE'
            );
            $requestStmt->execute([
                'id' => $requestId,
                'receptor_id' => $userId,
            ]);
            $request = $requestStmt->fetch();

            if (!$request) {
                $pdo->rollBack();

                return [
                    'success' => false,
                    'message' => 'No existe esa solicitud o ya fue respondida.',
                ];
            }

            if ($decision === 'accept') {
                [$user1, $user2] = self::orderedFriendPair((int) $request['emisor_id'], (int) $request['receptor_id']);

                $friendStmt = $pdo->prepare(
                    'INSERT IGNORE INTO amistades (usuario1_id, usuario2_id)
                     VALUES (:usuario1_id, :usuario2_id)'
                );
                $friendStmt->execute([
                    'usuario1_id' => $user1,
                    'usuario2_id' => $user2,
                ]);
            }

            $deleteStmt = $pdo->prepare('DELETE FROM solicitudes_amistad WHERE id = :id');
            $deleteStmt->execute([
                'id' => $requestId,
            ]);

            $pdo->commit();

            return [
                'success' => true,
                'message' => $decision === 'accept'
                    ? 'Solicitud aceptada. Ahora son amigos.'
                    : 'Solicitud rechazada.',
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function removeFriend(int $userId, int $friendUserId): array
    {
        self::ensureFriendshipsInfrastructure();

        if ($friendUserId <= 0 || $friendUserId === $userId) {
            return [
                'success' => false,
                'message' => 'Amigo invalido.',
            ];
        }

        [$user1, $user2] = self::orderedFriendPair($userId, $friendUserId);

        $stmt = db()->prepare(
            'DELETE FROM amistades WHERE usuario1_id = :usuario1_id AND usuario2_id = :usuario2_id'
        );
        $stmt->execute([
            'usuario1_id' => $user1,
            'usuario2_id' => $user2,
        ]);

        if ($stmt->rowCount() === 0) {
            return [
                'success' => false,
                'message' => 'Ese jugador no esta en tu lista de amigos.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Amigo eliminado correctamente.',
        ];
    }

    public static function getFriends(int $userId): array
    {
        self::ensureFriendshipsInfrastructure();

        $stmt = db()->prepare(
            "SELECT u.id,
                    u.username,
                    u.trofeos,
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM partidas p
                            WHERE p.estado = 'en_curso'
                              AND (p.jugador1_id = u.id OR p.jugador2_id = u.id)
                            LIMIT 1
                        ) THEN 1
                        ELSE 0
                    END AS jugando_ahora,
                    (
                        SELECT p.tipo
                        FROM partidas p
                        WHERE p.estado = 'en_curso'
                          AND (p.jugador1_id = u.id OR p.jugador2_id = u.id)
                        ORDER BY p.id DESC
                        LIMIT 1
                    ) AS tipo_partida,
                    a.fecha_creacion
             FROM amistades a
             INNER JOIN usuarios u
                 ON u.id = CASE WHEN a.usuario1_id = :user_case THEN a.usuario2_id ELSE a.usuario1_id END
             WHERE a.usuario1_id = :user_filter_1 OR a.usuario2_id = :user_filter_2
             ORDER BY jugando_ahora DESC, u.username ASC"
        );
        $stmt->execute([
            'user_case' => $userId,
            'user_filter_1' => $userId,
            'user_filter_2' => $userId,
        ]);

        $friends = [];

        foreach ($stmt->fetchAll() as $row) {
            $friends[] = [
                'id' => (int) $row['id'],
                'username' => (string) $row['username'],
                'trofeos' => (int) $row['trofeos'],
                'playing_now' => (int) $row['jugando_ahora'] === 1,
                'playing_type' => $row['tipo_partida'] !== null ? (string) $row['tipo_partida'] : null,
                'created_at' => (string) $row['fecha_creacion'],
            ];
        }

        return $friends;
    }

    private static function ensureFriendshipsInfrastructure(): void
    {
        self::ensureFriendshipsTable();
        self::ensureFriendRequestsTable();
    }

    private static function ensureFriendshipsTable(): void
    {
        if (self::$friendshipsTableEnsured) {
            return;
        }

        db()->exec(
            "CREATE TABLE IF NOT EXISTS amistades (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario1_id INT NOT NULL,
                usuario2_id INT NOT NULL,
                fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_amistad_par (usuario1_id, usuario2_id),
                CONSTRAINT fk_amistad_usuario1 FOREIGN KEY (usuario1_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                CONSTRAINT fk_amistad_usuario2 FOREIGN KEY (usuario2_id) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );

        self::$friendshipsTableEnsured = true;
    }

    private static function ensureFriendRequestsTable(): void
    {
        if (self::$friendRequestsTableEnsured) {
            return;
        }

        db()->exec(
            "CREATE TABLE IF NOT EXISTS solicitudes_amistad (
                id INT AUTO_INCREMENT PRIMARY KEY,
                emisor_id INT NOT NULL,
                receptor_id INT NOT NULL,
                fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_solicitud_direccion (emisor_id, receptor_id),
                CONSTRAINT fk_solicitud_emisor FOREIGN KEY (emisor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                CONSTRAINT fk_solicitud_receptor FOREIGN KEY (receptor_id) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );

        self::$friendRequestsTableEnsured = true;
    }

    private static function areFriends(int $userA, int $userB): bool
    {
        [$user1, $user2] = self::orderedFriendPair($userA, $userB);

        $stmt = db()->prepare(
            'SELECT id FROM amistades WHERE usuario1_id = :usuario1_id AND usuario2_id = :usuario2_id LIMIT 1'
        );
        $stmt->execute([
            'usuario1_id' => $user1,
            'usuario2_id' => $user2,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private static function orderedFriendPair(int $userA, int $userB): array
    {
        if ($userA < $userB) {
            return [$userA, $userB];
        }

        return [$userB, $userA];
    }
}
