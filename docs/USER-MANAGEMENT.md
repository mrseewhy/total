# User management

Open `/site-admin/users` while signed in as an Admin. The sidebar hides Users from Managers; PHP independently enforces all permissions.

## Roles

| Capability | Admin | Manager |
|---|---|---|
| Dashboard, subscribers/export, contact inbox/read status | Yes | Yes |
| Own profile and password | Yes | Yes |
| List/create/edit/ban/unban/delete users | Yes | No |

There is no public registration. CLI first-admin creation still works. New users created in the dashboard are active Managers. Give the initial password to the intended user through an appropriate private channel. No invitation email is sent.

## Operations

- Create: name, unique email, Manager role, password and confirmation. Password rules are the same as authentication (12–72 bytes).
- Edit: change name. Email and role are immutable in the dashboard. Leave the new-password fields blank to retain the existing password.
- Ban: sets `is_active` false. The account cannot log in, use private endpoints, or reset its password while banned.
- Unban: restores access but requires a fresh login; old sessions stay invalid.
- Delete: opens a native modal with Cancel and explicit permanent-delete confirmation. Deletes the account and reset tokens. Existing subscriber/contact records are retained because they are not owned by login accounts.

Email/password/role/status changes increment `session_version` and clear outstanding reset links. Deletion makes future session lookups fail. Admins editing their own password get a fresh current session; other sessions are invalidated. Name-only changes do not force logout.

You cannot ban, delete, or demote yourself. The last active Admin cannot be removed, banned, or demoted. Database transactions lock a singleton `user_management_lock` row so concurrent user-management requests cannot jointly remove the last active Admin.

## Files and routes

- `php/controllers/UserController.php`: Admin/CSRF enforcement, responses, management throttling.
- `php/validation/UserValidation.php`: validates and normalizes user form data.
- `php/models/User.php`: paginated listing and transactional account operations.
- `php/migrations/006_user_management_lock.php`: creates the serialization lock row.
- `src/api/users.ts`: authenticated API requests.
- `src/pages/admin/AdminUsers.tsx`: paginated list (20 per page), actions, modal state.
- `src/components/admin/UserEditor.tsx`: create/edit form modal.
- `src/components/admin/DeleteUserModal.tsx`: permanent-delete confirmation.
- `src/components/admin/AdminModal.tsx`: shared native dialog with focus containment, Escape handling, and focus restoration.

| Method | URL | Purpose |
|---|---|---|
| GET | `/api/users?page=1` | Paginated users, excluding hashes/session versions |
| POST | `/api/users` | Create account |
| PATCH | `/api/users/{id}` | Edit details and optional password |
| PATCH | `/api/users/{id}/status` | JSON `is_active` boolean for ban/unban |
| DELETE | `/api/users/{id}` | Permanently delete account |

All routes require Admin; all mutations require CSRF. Account changes are limited to 60 attempts per acting Admin per 10 minutes. No password hash is returned by the listing or session APIs.

A fresh deployment must run `php php/migrate.php migrate`. The local migration was applied during implementation. API tests used disposable accounts and verified Manager restrictions, mutations, session revocation, CSRF, duplicates, and self-protection. Last-active-Admin protection was separately checked against an isolated SQLite database.

Owner policy: only the owner Admin manages users. New accounts are Managers, account roles cannot be changed through dashboard APIs, and other Admin accounts cannot be managed by a different user. Email is read-only in both profile and user-management forms, enforced by PHP.

## Optional email two-factor authentication

Users can opt in through Profile settings; every account starts with it disabled. Enabling/disabling requires the current password and an emailed confirmation code. Enabled sign-ins require an email code after the password; other accounts sign in normally. See [2FA setup/recovery](TWO-FACTOR.md) and [permanent integration tests](TESTING.md).
