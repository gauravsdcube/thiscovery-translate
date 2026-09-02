<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryTranslate\permissions;

use humhub\modules\admin\components\BaseAdminPermission;

class TranslateResponses extends BaseAdminPermission
{
    protected $id = 'thiscovery_translate_responses';
    protected $moduleId = 'thiscovery-translate';
    protected $title = 'Translate responses';
    protected $description = 'Optionally machine-translate free-text form responses';
}
