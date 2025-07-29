<?php

namespace Platform\Planner\Enums;

enum ProjectRole: string {
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MEMBER = 'member';
    case VIEWER = 'viewer';
}