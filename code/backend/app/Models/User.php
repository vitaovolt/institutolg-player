<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'ativo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ativo' => 'boolean',
        ];
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ativo' => true,
    ];

    public function podeGerirAcervo(): bool
    {
        return $this->ativo === true;
    }

    public function podeVerOpsArmazenamento(): bool
    {
        $email = strtolower(trim((string) $this->email));
        /** @var list<string> $permitidos */
        $permitidos = config('biblioteca.ops_emails', []);

        return $email !== '' && in_array($email, $permitidos, true);
    }

    /**
     * @return array{id: int, name: string, email: string, pode_ver_ops: bool}
     */
    public function toAuthArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'pode_ver_ops' => $this->podeVerOpsArmazenamento(),
        ];
    }
}
