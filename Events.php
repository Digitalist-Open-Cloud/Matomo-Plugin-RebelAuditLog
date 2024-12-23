<?php

namespace Piwik\Plugins\RebelAuditLog;

final class Events
{
    public const AUTHENTICATE_SUCCESSFUL = 'Login.authenticate.successful';
    public const AUTHENTICATE_FAILED = 'Login.authenticate.failed';
    public const BOT_TRACKER_INSERT_BOT = 'BotTracker.insertBot.successful';
    public const BOT_TRACKER_UPDATE_BOT = 'BotTracker.updateBot.successful';
    public const BOT_TRACKER_DELETE_BOT = 'BotTracker.deleteBot.successful';
    public const CORE_CONFIG_FILE_CHANGED = 'Core.configFileChanged';
    public const CUSTOM_ALERTS_ADDED_ALERT = 'API.CustomAlerts.addAlert.end';
    public const CUSTOM_ALERTS_EDIT_ALERT = 'API.CustomAlerts.editAlert.end';
    public const CUSTOM_ALERTS_DELETE_ALERT = 'API.CustomAlerts.deleteAlert.end';
    public const CUSTOM_DIMENSIONS_NEW_DIMENSION = 'API.CustomDimensions.configureNewCustomDimension.end';
    public const CUSTOM_DIMENSIONS_CONFIGURE_DIMENSION = 'API.CustomDimensions.configureExistingCustomDimension.end';
    public const DASHBOARD_COPY = 'API.Dashboard.copyDashboardToUser.end';
    public const DASHBOARD_CREATE_NEW = 'API.Dashboard.createNewDashboardForUser.end';
    public const DASHBOARD_REMOVED = 'API.Dashboard.removeDashboard.end';
    public const DASHBOARD_RESET_LAYOUT = 'API.Dashboard.resetDashboardLayout.end';
    public const GOALS_ADD_GOAL = 'API.Goals.addGoal.end';
    public const GOALS_DELETE_GOAL = 'API.Goals.deleteGoal.end';
    public const GOALS_UPDATE_GOAL = 'API.Goals.updateGoal.end';
    public const PLUGIN_ACTIVATED = 'PluginManager.pluginActivated';
    public const PLUGIN_DEACTIVATED = 'PluginManager.pluginDeactivated';
    public const PLUGIN_INSTALLED = 'PluginManager.pluginInstalled';
    public const PLUGIN_UNINSTALLED = 'PluginManager.pluginUninstalled';
    public const PRIVACY_MANAGER_ACTIVATE_DO_NOT_TRACK = 'API.PrivacyManager.activateDoNotTrack.end';
    public const PRIVACY_MANAGER_DEACTIVATE_DO_NOT_TRACK = 'API.PrivacyManager.deactivateDoNotTrack.end';
    public const PRIVACY_MANAGER_SET_ANONYMIZE_IP = 'API.PrivacyManager.setAnonymizeIpSettings.end';
    public const PRIVACY_MANAGER_EXECUTE_DATA_PURGE = 'API.PrivacyManager.executeDataPurge.end';
    public const PRIVACY_MANAGER_SET_DELETE_LOGS_SETTINGS = 'API.PrivacyManager.setDeleteLogsSettings.end';
    public const PRIVACY_MANAGER_SET_DELETE_REPORTS_SETTINGS = 'API.PrivacyManager.setDeleteReportsSettings.end';
    public const PRIVACY_MANAGER_SET_SCHEDULE_REPORT_DELETION_SETTINGS =
      'API.PrivacyManager.setScheduleReportDeletionSettings.end';
    public const PRIVACY_MANAGER_ANONYMIZE_SOME_RAW_DATA = 'API.PrivacyManager.anonymizeSomeRawData.end';
    public const PRIVACY_MANAGER_DELETE_DATA_SUBJECTS = 'API.PrivacyManager.deleteDataSubjects.end';
    public const PRIVACY_MANAGER_EXPORT_DATA_SUBJECTS = 'API.PrivacyManager.exportDataSubjects.end';
    public const SEGMENT_EDITOR_DEACTIVATED_SEGMENT = 'SegmentEditor.deactivate';
    public const SEGMENT_EDITOR_ADDED_SEGMENT = 'API.SegmentEditor.add.end';
    public const SEGMENT_EDITOR_UPDATED_SEGMENT = 'API.SegmentEditor.update.end';
    public const SITES_MANAGER_ADDED_SITE = 'API.SitesManager.addSite.end';
    public const SITES_MANAGER_DELETE_SITE = 'API.SitesManager.deleteSite.end';
    public const SITES_MANAGER_UPDATE_SITE = 'API.SitesManager.updateSite.end';
    public const USERS_MANAGER_ADD_CAPABILITIES = 'API.UsersManager.addCapabilities.end';
    public const USERS_MANAGER_ADDED_USER = 'UsersManager.addUser.end';
    public const USERS_MANAGER_INVITED_USER = 'UsersManager.inviteUser.end';
    public const USERS_MANAGER_SET_ACCESS = 'API.UsersManager.setUserAccess.end';
    public const USERS_MANAGER_SET_SUPER = 'API.UsersManager.setSuperUserAccess.end';
}
