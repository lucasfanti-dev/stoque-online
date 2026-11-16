<?php

namespace App\Models;

use App\Core\Database;

class Company
{
    /** Colunas que o formulário do cliente pode gravar */
    private const FILLABLE = [
        'company_name', 'trading_name', 'cnpj', 'address', 'number', 'district',
        'city', 'state', 'zip_code', 'phone', 'cell_phone', 'whatsapp',
        'contact_email', 'website', 'facebook', 'instagram', 'linkedin',
        'youtube', 'twitter', 'skype',
    ];

    public static function findByUserId(int $userId): ?array
    {
        return Database::fetch("SELECT * FROM companies WHERE user_id = :user_id LIMIT 1", ['user_id' => $userId]);
    }

    public static function saveOrUpdate(int $userId, array $data): void
    {
        // Só colunas conhecidas chegam ao SQL, mesmo que o formulário seja adulterado
        $data = array_intersect_key($data, array_flip(self::FILLABLE));

        if (self::findByUserId($userId)) {
            Database::update('companies', $data, 'user_id = :user_id', ['user_id' => $userId]);
            return;
        }

        $data['user_id'] = $userId;
        Database::insert('companies', $data);
    }
}
