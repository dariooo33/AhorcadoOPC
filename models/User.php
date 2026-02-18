<?php
declare(strict_types=1);

class User
{
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
}
