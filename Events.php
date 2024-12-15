<?php

namespace Piwik\Plugins\RebelAuditLog;

final class Events
{
    public const AB_TESTING_ADD_EXPERIMENT = 'API.AbTesting.addExperiment.end';
    public const AUTHENTICATE_SUCCESSFUL = 'Login.authenticate.successful';
    public const AUTHENTICATE_FAILED = 'Login.authenticate.failed';
    public const PLUGIN_ACTIVATED = 'PluginManager.pluginActivated';
    public const PLUGIN_DEACTIVATED = 'PluginManager.pluginDeactivated';
    public const PLUGIN_INSTALLED = 'PluginManager.pluginInstalled';
    public const PLUGIN_UNINSTALLED = 'PluginManager.pluginUninstalled';
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
