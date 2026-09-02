<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\permissions;

use humhub\modules\admin\components\BaseAdminPermission;

class ManageTerminology extends BaseAdminPermission
{
    protected $id = 'thiscovery_translate_manage_terminology';
    protected $moduleId = 'thiscovery-translate';
    protected $title = 'Manage terminology';
    protected $description = 'Manage protected terms and preferred translations';
}
