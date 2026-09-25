<?php

namespace App\Enums;

/**
 * Every permission the application knows about.
 *
 * Nothing reads these yet: authorization is deliberately not built. They exist so
 * that when it lands it has exactly one place to live, and so roles can be defined
 * against a closed set rather than free-form strings.
 */
enum Permission: string
{
    case EmailsView = 'emails.view';
    case EmailsManage = 'emails.manage';
    case KnowledgeView = 'knowledge.view';
    case KnowledgeManage = 'knowledge.manage';
    case AccountManage = 'account.manage';
    case MembersManage = 'members.manage';
}
