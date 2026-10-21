<?php

declare(strict_types=1);

namespace App\Models;

class CustomerModel
{
    private ?int $id;
    private string $name;
    private string $email;
    private string $phone;
    private string $address;
    private string $createdAt;
    private string $passwordHash;

    public function __construct(?int $id, string $name, string $email, string $phone, string $address, ?string $createdAt = null, string $passwordHash = '')
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->phone = $phone;
        $this->address = $address;
        $this->createdAt = $createdAt ?? date('Y-m-d H:i:s');
        $this->passwordHash = $passwordHash;
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

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPassword(string $plainPassword): void
    {
        $this->passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
    }

    public function verifyPassword(string $plainPassword): bool
    {
        if ($this->passwordHash === '') {
            return false;
        }
        return password_verify($plainPassword, $this->passwordHash);
    }

    /**
     * Validates customer fields one by one and returns an array of
     * field => message errors. An empty array means everything is valid.
     */
    public static function validateData(string $name, string $email, string $phone, string $address): array
    {
        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Please enter the full name.';
        } elseif (mb_strlen($name) < 2) {
            $errors['name'] = 'Name must be at least 2 characters long.';
        } elseif (mb_strlen($name) > 50) {
            $errors['name'] = 'Name cannot be longer than 50 characters.';
        }

        if ($email === '') {
            $errors['email'] = 'Please enter an email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'This email address does not look valid. Example: name@example.com';
        } elseif (mb_strlen($email) > 100) {
            $errors['email'] = 'Email cannot be longer than 100 characters.';
        }

        if ($phone === '') {
            $errors['phone'] = 'Please enter a phone number.';
        } elseif (!preg_match('/^[0-9+\-() ]{7,15}$/', $phone)) {
            $errors['phone'] = 'Phone number must be 7 to 15 digits (spaces, dashes and brackets are allowed).';
        }

        if ($address === '') {
            $errors['address'] = 'Please enter an address.';
        } elseif (mb_strlen($address) > 255) {
            $errors['address'] = 'Address cannot be longer than 255 characters.';
        }

        return $errors;
    }

    public function isValid(): bool
    {
        return self::validateData(trim($this->name), trim($this->email), trim($this->phone), trim($this->address)) === [];
    }

    public function save(): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        if ($this->id === null) {
            return $this->create();
        }
        return $this->update();
    }

    public function create(): bool
    {
        $created = supa_insert_customer([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'created_at' => $this->createdAt,
            'password_hash' => $this->passwordHash,
        ]);

        if ($created === null) {
            error_log('Failed to create customer.');
            return false;
        }

        $this->id = (int) $created['id'];
        return true;
    }

    public function remove(): bool
    {
        if ($this->id === null) {
            return false;
        }
        return supa_delete_customer($this->id);
    }

    public function update(): bool
    {
        if ($this->id === null) {
            return false;
        }

        return supa_update_customer($this->id, [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'created_at' => $this->createdAt,
            'password_hash' => $this->passwordHash,
        ]);
    }

    public static function findById(int $id): ?CustomerModel
    {
        $data = supa_find_customer_by_id($id);
        return $data ? self::fromRow($data) : null;
    }

    public static function findByEmail(string $email): ?CustomerModel
    {
        $data = supa_find_customer_by_email($email);
        return $data ? self::fromRow($data) : null;
    }

    private static function fromRow(array $data): CustomerModel
    {
        return new CustomerModel(
            (int) $data['id'],
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['address'],
            $data['created_at'],
            $data['password_hash'] ?? ''
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'createdAt' => $this->createdAt,
        ];
    }

}