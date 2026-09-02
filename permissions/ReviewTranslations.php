<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\permissions;

use humhub\modules\admin\components\BaseAdminPermission;

class ReviewTranslations extends BaseAdminPermission
{
    protected $id = 'thiscovery_translate_review';
    protected $moduleId = 'thiscovery-translate';
    protected $title = 'Review translations';
    protected $description = 'Review and verify machine translations';
}
