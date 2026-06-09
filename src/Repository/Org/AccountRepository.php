<?php
 
namespace App\Repository\Org;
 
use PDO;
 
class AccountRepository
{
    private PDO $db;
 
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
 
    public function getAccounts(): array
    {
        $query = $this->db->prepare("
            SELECT *
            FROM accounts
            ORDER BY id DESC
        ");
 
        $query->execute();
 
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }
 
    public function getAccount(int $id): ?array
    {
        $query = $this->db->prepare("
            SELECT *
            FROM accounts
            WHERE id = :id
        ");
 
        $query->execute([
            'id' => $id
        ]);
 
        $account = $query->fetch(PDO::FETCH_ASSOC);
 
        return $account ?: null;
    }
 
    public function updateAccount(int $id, array $data): bool
    {
        $query = $this->db->prepare("
            UPDATE accounts
            SET
                name = :name,
                email = :email,
                role = :role,
                active = :active
            WHERE id = :id
        ");
 
        return $query->execute([
            'id' => $id,
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'active' => $data['active']
        ]);
    }
}