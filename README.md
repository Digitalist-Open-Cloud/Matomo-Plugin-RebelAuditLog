# Rebel Audit Log (for Matomo)

[![Test plugin with Matomo](https://github.com/Digitalist-Open-Cloud/Matomo-Plugin-RebelAuditLog/actions/workflows/matomo.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/Matomo-Plugin-RebelAuditLog/actions/workflows/matomo.yaml)
[![PHPCS check](https://github.com/Digitalist-Open-Cloud/Matomo-Plugin-RebelAuditLog/actions/workflows/phpcs.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/Matomo-Plugin-RebelAuditLog/actions/workflows/phpcs.yaml)
[![Semgrep OSS scan](https://github.com/Digitalist-Open-Cloud/Matomo-Plugin-RebelAuditLog/actions/workflows/semgrep.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/Matomo-Plugin-RebelAuditLog/actions/workflows/semgrep.yaml)

An audit log for Matomo: who changed what, and when. It is stored in Matomo's database and
can be read by super users only.

## What is logged

- Logins, successful and failed.
- Users: added, invited, access, super user access and capabilities changed.
- Websites, goals, segments, annotations, dashboards and custom dimensions: added, changed
  and deleted.
- Privacy settings, GDPR tools and invalidated reports.
- Plugins activated, deactivated, installed and uninstalled.
- System settings saved and the config file changed.
- Tag Manager: containers, tags, triggers, variables and versions.
- Custom Alerts, Group Permissions and Bot Tracker, when those plugins are installed.

Each entry has the user, the IP address, the time, a sentence that tells what happened and the
values that were sent, as details.

Passwords, tokens, keys and other secrets are never logged: a value whose name tells that it
is one is stored as `***`, and so is a system setting that is a password field.

An action from the command line is logged for the user `Console`.

## Install

Install it from the Matomo Marketplace, or put this repository in `plugins/RebelAuditLog` and
activate it:

```sh
./console plugin:activate RebelAuditLog
```

## Use

Administration > Audit log shows the entries of a period, newest first. They
can be filtered by user and by event, and exported as CSV with their details.

## API

`RebelAuditLog.getAudits` returns the entries with their details as further columns, in any
format of Matomo's API. It needs super user access.

| Parameter | Meaning |
| --- | --- |
| `offset`, `limit` | Which entries, by default the first 50000. |
| `order` | `ASC` for the oldest first (default) or `DESC`. |
| `selectedUser` | Only the entries of this user. |
| `selectedEventBase` | Only the entries of this event, eg, `UsersManager`. |
| `excludeConsole` | `1` to leave out what was done from the command line. |
| `startDate`, `endDate` | Only the entries of these days, as `YYYY-MM-DD`. |

## Adding an event

The events are listed in `EventLogger.php`. For an API method of Matomo or a plugin, add a line
to `API_MESSAGES` with the sentence to log; `{name}` stands for the parameter `name` of the
method. For any other event, add it to `OTHER_EVENTS` with a method that logs it.

## License

GPL v3 or later, see [LICENSE.md](LICENSE.md).
