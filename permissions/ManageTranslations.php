<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\permissions;

use humhub\modules\admin\components\BaseAdminPermission;

class ManageTranslations extends BaseAdminPermission
{
    protected $id = 'thiscovery_translate_manage_translations';
    protected $moduleId = 'thiscovery-translate';
    protected $title = 'Manage translations';
    protected $description = 'Edit and lock stored translations and translation memory';
}
