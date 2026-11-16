<?php

namespace App\Models;

use App\Core\Database;

class ContactPerson
{
    public static function getByUserId(int $userId): array
    {
        return Database::fetchAll(
            "SELECT * FROM contacts WHERE user_id = :user_id ORDER BY name ASC, id ASC",
            ['user_id' => $userId]
        );
    }

    public static function countIncludedInLicense(int $userId): int
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM contacts WHERE user_id = :user_id AND include_in_license = 1",
            ['user_id' => $userId],
            0
        );
    }

    public static function existsByEmail(int $userId, string $email): bool
    {
        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM contacts WHERE user_id = :user_id AND email = :email",
            ['user_id' => $userId, 'email' => $email],
            0
        ) > 0;
    }

    public static function create(array $data): int
    {
        return Database::insert('contacts', $data);
    }

    public static function delete(int $id, int $userId): int
    {
        return Database::delete('contacts', 'id = :id AND user_id = :user_id', [
            'id'      => $id,
            'user_id' => $userId,
        ]);
    }

    public static function toggleLicense(int $id, int $userId, int $include): int
    {
        return Database::update(
            'contacts',
            ['include_in_license' => $include],
            'id = :id AND user_id = :user_id',
            ['id' => $id, 'user_id' => $userId]
        );
    }
}
