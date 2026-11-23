# Rebel Audit Log (for Matomo)

[![Test plugin with Matomo](https://github.com/Digitalist-Open-Cloud/Matomo-Plugin-RebelAuditLog/actions/workflows/matomo.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/Matomo-Plugin-RebelAuditLog/actions/workflows/matomo.yaml)
[![PHPCS check](https://github.com/Digitalist-Open-Cloud/Matomo-Plugin-RebelAuditLog/actions/workflows/phpcs.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/Matomo-Plugin-RebelAuditLog/actions/workflows/phpcs.yaml)
[![Semgrep OSS scan](https://github.com/Digitalist-Open-Cloud/Matomo-Plugin-RebelAuditLog/actions/workflows/semgrep.yaml/badge.svg)](https://github.com/Digitalist-Open-Cloud/Matomo-Plugin-RebelAuditLog/actions/workflows/semgrep.yaml)

An audit log for Matomo: who changed what, and when. It is stored in Matomo's database and
can be read by super users only.

## What is logged

- Logins, successful and failed, logouts and password resets.
- Users: added, invited, changed, deleted, access, super user access and capabilities changed.
- API tokens created and deleted, and two-factor authentication turned on or off.
- Scheduled email reports added, changed, deleted and sent by hand.
- Reports of RebelMyReports, funnels of RebelFunnels and experiments of RebelExperiments added,
  changed and deleted.
- Websites, goals, segments, annotations, dashboards and custom dimensions: added, changed
  and deleted.
- Privacy settings, GDPR tools and invalidated reports.
- Plugins activated, deactivated, installed and uninstalled.
- System settings saved and the config file changed.
- Tag Manager: containers, tags, triggers, variables and versions.
- Custom Alerts, Group Permissions and Bot Tracker, when those plugins are installed.
- Forms of UserFeedback added, changed, copied and deleted, and feedback answers deleted.
- Ads and clients of RebelAds added, changed and deleted.
- Names anonymized with DataCleanerExtended, and its alarms added and deleted.
- Notifications added, changed and deleted with RebelNotifications.

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

## How long entries are kept

By default entries are kept forever. A super user can set "Months to keep entries" under
Administration > System > General settings, in the RebelAuditLog section. Older entries are
then deleted once a day, and the deletion is logged. The log holds IP addresses and logins,
which are personal data.

## External database

The log can also be written to a database outside Matomo, so that it does not depend on
Matomo's own database and cannot be changed from within Matomo. MariaDB, MySQL and PostgreSQL
are supported; PostgreSQL needs PHP's `pdo_pgsql` extension. Add to `config/config.ini.php`:

```ini
[RebelAuditLog]
; "mysql:host=...;port=3306;dbname=..." or "pgsql:host=...;port=5432;dbname=..."
external_dsn = "pgsql:host=audit.example.org;port=5432;dbname=audit"
external_username = "<user>"
external_password = "<password>"
; Optional: a prefix for the two tables.
external_table_prefix = ""
; Optional: tells the entries of this Matomo from others in the same database.
; By default the host name of Matomo.
external_source = ""
```

Create the tables and check the connection:

```sh
./console rebelauditlog:external-db --create-tables
./console rebelauditlog:external-db
```

`--print-sql` prints the statements instead, for a database where Matomo's user may not create
tables. After that the user only needs to insert into the two tables.

Every entry is written to Matomo's database first and then to the external one, with its
details. The page and the API read from Matomo's database. If the external database cannot be
reached, the entry is still in Matomo's database, the failure is reported in Matomo's log and
what the user did goes on; such an entry is not sent again later. Deleting old entries does
not touch the external database.

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
