<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\permissions;

use humhub\modules\admin\components\BaseAdminPermission;

class ManageTranslationSettings extends BaseAdminPermission
{
    protected $id = 'thiscovery_translate_manage_settings';
    protected $moduleId = 'thiscovery-translate';
    protected $title = 'Manage translation settings';
    protected $description = 'Configure Thiscovery Translate provider, languages, and budgets';
    protected $defaultState = self::STATE_ALLOW;
}
