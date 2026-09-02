<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\permissions;

use humhub\modules\admin\components\BaseAdminPermission;

class ViewTranslationUsage extends BaseAdminPermission
{
    protected $id = 'thiscovery_translate_view_usage';
    protected $moduleId = 'thiscovery-translate';
    protected $title = 'View translation usage';
    protected $description = 'View Amazon Translate usage and cost metrics';
}
