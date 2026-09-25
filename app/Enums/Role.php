<?php

namespace App\Enums;

/**
 * Roles are defined here rather than in a table: there is no roles administration
 * screen, so rows would only be a lookup table kept in sync with these constants by
 * a seeder. The pivot column account_user.role casts to this enum, which validates
 * the value at the type level instead of with a foreign key.
 *
 * Nothing reads these yet. See the multi-tenancy docs before wiring them up.
 */
enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    /**
     * The permissions this role grants.
     *
     * Note that account_user.is_admin overrides this entirely within its account,
     * as does users.is_admin across the whole platform.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner, self::Admin => Permission::cases(),
            self::Member => [
                Permission::EmailsView,
                Permission::EmailsManage,
                Permission::KnowledgeView,
                Permission::KnowledgeManage,
            ],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Admin => __('Administrator'),
            self::Member => __('Member'),
        };
    }
}
