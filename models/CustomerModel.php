<?php

declare(strict_types=1);

namespace App\Domain\Models;

use PDO;
use PDOException;

class CustomerModel
{
    private ?int $id;
    private string $name;
    private string $email;
    private string $phone;
    private string $address;

    private static ?PDO $dbConnection = null;

    public function __construct(?int $id, string $name, string $email, string $phone, string $address)
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->phone = $phone;
        $this->address = $address;
    }

    public static function setDbConnection(PDO $connection): void
    {
        self::$dbConnection = $connection;
    }

    private static function getDbConnection(): PDO
    {
        if (self::$dbConnection === null) {
            throw new PDOException('Database connection is not set.');
        }
        return self::$dbConnection;
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setPhone(string $phone): void
    {
        $this->phone = $phone;
    }

    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    public function isValid(): bool
    {
        if (trim($this->name) === '') {
            return false;
        }
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if (trim($this->phone) === '') {
            return false;
        }
        if (trim($this->address) === '') {
            return false;
        }
        return true;
    }

    public function save(): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        try {
            if ($this->id === null) {
                return $this->create();
            }
            return $this->update();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }
    public function create(): bool
    {
        $stmt = self::getDbConnection()->prepare(
            'INSERT INTO customers (name, email, phone, address) VALUES (:name, :email, :phone, :address)'
        );
        $success = $stmt->execute([
            ':name' => $this->name,
            ':email' => $this->email,
            ':phone' => $this->phone,
            ':address' => $this->address,
        ]);

        if ($success) {
            $this->id = (int) self::getDbConnection()->lastInsertId();
        } else {
            error_log('Failed to create customer: ');
        }
        return $success;
    }

    public function remove(): bool
    {
        if ($this->id === null) {
            return false;
        }

        $stmt = self::getDbConnection()->prepare('DELETE FROM customers WHERE id = :id');
        return $stmt->execute([':id' => $this->id]);
    }

    public function update(): bool
    {
        if ($this->id === null) {
            return false;
        }

        $stmt = self::getDbConnection()->prepare(
            'UPDATE customers SET name = :name, email = :email, phone = :phone, address = :address WHERE id = :id'
        );
        return $stmt->execute([
            ':id' => $this->id,
            ':name' => $this->name,
            ':email' => $this->email,
            ':phone' => $this->phone,
            ':address' => $this->address,
        ]);
    }

    public static function findById(int $id): ?CustomerModel
    {
        $stmt = self::getDbConnection()->prepare('SELECT * FROM customers WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            return new CustomerModel(
                (int) $data['id'],
                $data['name'],
                $data['email'],
                $data['phone'],
                $data['address']
            );
        }
        return null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
        ];
    }

}