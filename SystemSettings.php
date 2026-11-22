<?php

/**
 * The Rebel Audit Log plugin for Matomo.
 *
 * Copyright (C) 2024 Digitalist Open Cloud <cloud@digitalist.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Piwik\Plugins\RebelAuditLog;

use Exception;
use Piwik\Piwik;
use Piwik\Settings\FieldConfig;

class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{
    public const KEEP_MONTHS = 'keep_months';

    protected function init()
    {
        $this->makeSetting(self::KEEP_MONTHS, 0, FieldConfig::TYPE_INT, function (FieldConfig $field) {
            $field->title = Piwik::translate('RebelAuditLog_SettingKeepMonths');
            $field->inlineHelp = Piwik::translate('RebelAuditLog_SettingKeepMonthsHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->validate = function ($value) {
                if (!is_numeric($value) || $value < 0 || (int) $value != $value) {
                    throw new Exception(Piwik::translate('RebelAuditLog_ErrorKeepMonths'));
                }
            };
        });
    }

    /**
     * @return int for how many months entries are kept, 0 for always
     */
    public function getKeepMonths(): int
    {
        return max(0, (int) $this->getSetting(self::KEEP_MONTHS)->getValue());
    }
}
