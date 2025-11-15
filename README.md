# IT Support Ticket System – Use Case Specification

This document captures the functional and non-functional requirements gathered for the IT Support Ticket System, focusing on seven core use cases plus supporting process and data models. It replaces the stock Laravel README so that the repository can double as a requirements handoff for design, engineering, QA, and audit teams.

## 1. System Overview

- **Goal**: Provide a secure, auditable, and role-aware workflow for capturing, managing, and resolving IT support requests.
- **Scope**: Web portal (End-User), agent console (Support Staff/Manager), and administrative console (System Admin) covering authentication, ticket lifecycle, collaboration, dashboards, and auditability.
- **Key Qualities**: RBAC-first design, SLA awareness, detailed audit logging, near real-time status visibility, and extensibility toward integrations (IDP/LDAP, notification, analytics, cache).

## Implementation Snapshot – November 2025 Build

The current Laravel implementation maps the above requirements into the following end-to-end capabilities:

- **Multi-channel intake**: Tickets can originate from the web portal, API-connected email/phone/chat channels, or manual agent capture. Every ticket records its `channel`, `ingestion_reference`, requester metadata, and an audit trail entry (`created`/`ingested`).
- **Smart forms & KB nudges**: Portal forms now capture *impact*, *urgency*, attachments, and provide inline knowledge base suggestions before submission so end-users can self-resolve common issues.
- **Workflow automation**: Rule-based categorisation assigns `assignment_group`, auto-escalates priority based on the impact × urgency matrix, and seeds SLA timers (`response_due_at`, `sla_due_at`). Default assignees are picked per group to reduce triage toil.
- **Lifecycle coverage**: Tickets flow across *To Do → In Progress → Waiting → Testing → Done → Closed* (surfaced as Kanban columns). Each transition logs an auditable `ticket_status_histories` record with `event_type`, metadata, and the acting user.
- **Communication & collaboration**: Conversation tabs support public/internal notes, notify creators/assignees, and log every comment in the audit timeline.
- **Automation & SLA observability**: SLA breaches are recalculated whenever tickets update or receive staff responses. Breach badges show in list/board/detail screens, and dashboard KPIs track MTTR plus backlog by priority.
- **Reporting & dashboards**: The home dashboard now visualises status counts, SLA breaches, MTTR, priority backlog, and exposes quick jumps to Kanban/list/creation flows.

### Multi-channel intake API

```
POST /api/ingest/tickets
Headers:
    Content-Type: application/json
    X-Integration-Token: ${TICKET_INGESTION_TOKEN}

Body:
{
  "subject": "VPN disconnected every hour",
  "description": "Tunnel drops on Wi-Fi and LTE.",
  "channel": "email",
  "category": "network",
  "impact": "high",
  "urgency": "medium",
  "requester_email": "user@example.com",
  "requester_name": "Remote User",
  "metadata": {"message_id": "<abc123@example.com>"}
}
```

Successful requests return the new ticket id, assignment group, computed priority, SLA target, and knowledge base suggestions so external connectors can generate auto-responders.
Set `TICKET_INGESTION_TOKEN` in `.env`/secrets to protect this endpoint; tokens can be unique per channel or integration.

## 2. Actors & Roles

| Actor | Description | Representative Capabilities |
| --- | --- | --- |
| End-User | Employees/customers submitting and tracking tickets. | Submit tickets, view status, comment, reopen within policy. |
| Support Staff | Agents handling day-to-day tickets. | Assign/self-assign, update statuses, add internal/public notes, manage SLA timers. |
| Support Manager | Oversees queues and KPIs. | Reassign across teams, close tickets, configure SLA policies, access dashboards/exports. |
| System Admin | Highest privilege operator. | Manage users/roles/permissions, enforce policies, monitor integrations. |

## 3. Platform Dependencies

- **Core services**: User DB, Ticket DB, Notification Service, File Storage/Scanning, SLA/Timer Service, Cache/Analytics, Audit Log, Rate Limiter.
- **Security services**: IDP/LDAP, Session Store/JWT provider, RBAC/Policy engine, HTTPS/TLS termination.
- **Integrations**: Email/SMS/push providers, Knowledge Base, reporting/export pipelines.

## 4. Use Case Catalog

| ID | Name | Primary Actor | Goal |
| --- | --- | --- | --- |
| UC001 | Manage Users | System Admin | Full user lifecycle (CRUD, roles, activation, password reset). |
| UC002 | Submit Ticket via Portal | End-User | Capture support request with metadata and attachments. |
| UC003 | View Ticket Status | End-User | Track ticket progress, history, and communications. |
| UC004 | Authentication | All roles | Secure login, MFA/OTP, remember-me, logout. |
| UC005 | Dashboard | All roles | Real-time KPIs, SLA alerts, drill-down for decision making. |
| UC006 | Assign/Update Ticket | Support Staff/Manager | Manage ownership, state transitions, SLA timers, notifications. |
| UC007 | Comment/Conversation | End-User & Support Staff | Bi-directional communication, attachments, mentions, audit. |

---

## 5. Detailed Use Cases

### UC001 – Manage Users

- **Version**: 1.0 (19/08/2025)
- **Primary Actor**: System Admin
- **Secondary Actors**: End-User, Support Staff, Support Manager
- **Description**: Manage user accounts end-to-end (Create, Read, Update, Delete, Activate/Deactivate, Reset Password) along with role and permission assignments, enforcing password policy and uniqueness constraints.

**Pre-conditions**
1. Actor logged in with System Admin privileges (or equivalent).
2. Connectivity to User DB, Role/Permission service, and Audit Log.
3. Enforced password policy and email uniqueness rules available.

**Post-conditions**
- *Success*: User data persisted, audit log recorded, refreshed list with confirmation.
- *Failure*: No DB changes, secure error message indicating corrective fields.

**Main Flow**

| Step | Actor | System |
| --- | --- | --- |
| 1 | Admin selects User Management menu. | Verifies RBAC, loads paged user list. |
| 2 | Admin clicks “Create User”. | Presents entry form. |
| 3 | Admin enters/edits user data (name, email, role). | Validates format, required fields. |
| 4 | – | Checks duplicate email / password policy. |
| 5 | – | Persists user, assigns roles/permissions. |
| 6 | – | Logs `USER_CREATED`. |
| 7 | – | Refreshes list, shows success toast/alert. |

**Alternative Flow 3b – Deactivate / Reactivate**
1. Open User Detail → toggle status (validate pending work restrictions).
2. Confirm change → update status, revoke/restore sessions, log event, send notifications.
3. Resume with updated list/state.

**Alternative Flow 3c – Reset Password**
1. Select Reset Password.
2. Generate scoped token/link.
3. Confirm → send email with reset link, record audit entry, return to detail view.

**Exception Flow 5x – Duplicate Email/Username**
1. System flags duplicate during validation.
2. Admin revises data, resubmits.
3. System re-validates, returns to main flow.

**Business Rules**
- BR01: Email/username must be unique.
- BR02: Each user must have ≥1 approved role.
- BR03: Cannot delete the last remaining admin.
- BR04: Tickets assigned to user must be reassigned before deactivate/hard-delete.

**Non-Functional Requirements**

| Category | Requirement | Target |
| --- | --- | --- |
| Performance | Search/filter 10k users | < 3 s |
| Security | Audit every change | 100% |
| Usability | Bulk import/export CSV | Supported |
| Availability | User admin uptime | 99.9% |

**Additional Information**
- Frequency: Medium.
- Peak: Project kick-off / team restructuring.
- Priority: High.
- Related: Assign Roles & Permissions, Login/SSO, Manage Teams/Groups.
- Dependencies: DB, IDP/LDAP, Notification, Audit Logging.
- Revision History: 1.0 (11/08/2025) Initial.

---

### UC002 – Submit Ticket via Portal

- **Version**: 1.0 (19/08/2025)
- **Primary Actor**: End-User
- **Secondary Actors**: Support Staff, Support Manager, System Admin
- **Description**: Users submit IT issues via portal with subject, description, priority, category, urgency, and attachments.

**Pre-conditions**
1. End-User authenticated to portal.
2. System online with DB connectivity.
3. User has valid permissions.

**Post-conditions**
- *Success*: Ticket ID created, persisted, confirmations sent.
- *Failure*: Ticket rejected, informative error displayed without leaking internals.

**Main Flow**
1. End-User opens “Create New Ticket”.
2. Fills Subject, Description, Priority, Category.
3. Adds attachments (optional).
4. Clicks Submit → system validates completeness/accuracy.
5. System generates Ticket ID, saves record.
6. System dispatches notifications to Support roles and End-User.
7. User sees Ticket ID and details.

**Alternative Flow 2a – Save as Draft**
1. User picks “Save as Draft”.
2. System stores temporary data without Ticket ID.
3. Resume to draft list.

**Alternative Flow 3a – Multiple Attachments**
1. System checks count and total size.
2. Accepts and stores files if within policy; continue with submission.

**Exception Flow 4x – Incomplete Data**
1. System blocks submission, highlights missing fields.
2. User corrects data → back to Step 2.

**Exception Flow 5x – Database Error**
1. System shows error and aborts creation.

**Business Rules**
- BR01: Subject and Description mandatory.
- BR02: Priority must be selected.
- BR03: Attachments ≤ 5 files, ≤ 20 MB each.
- BR04: Ticket IDs unique and traceable.

**Non-Functional Requirements**

| Category | Requirement | Target |
| --- | --- | --- |
| Performance | Validate + Save | < 3 s |
| Security | Audit every ticket creation | 100% |
| Usability | Draft & multi-attachment support | Required |
| Availability | Portal submit uptime | 99.9% |

**Additional Information**
- Frequency: High; peak 09:00–11:00 and 16:00–18:00.
- Priority: High.
- Related: View Ticket Status, Update Ticket Status, Manage Users.
- Dependencies: DB, Notification, File Storage.
- Revision: 1.0 (11/08/2025).

---

### UC003 – View Ticket Status

- **Version**: 1.0 (19/08/2025)
- **Primary Actor**: End-User
- **Secondary Actors**: Support Staff, Support Manager, System Admin
- **Description**: End-Users review status, history, communication, and timeline for their tickets.

**Pre-conditions**
1. End-User authenticated.
2. Tickets exist for user.
3. DB/status services reachable.

**Post-conditions**
- *Success*: Latest status, history, and communication displayed.
- *Failure*: Error message (“Ticket Not Found” or data retrieval issue).

**Main Flow**
1. User opens “My Tickets”.
2. Selects target ticket.
3. System fetches ticket data.
4. Displays status (Open, In Progress, On Hold, Resolved, Closed).
5. Shows history/logs/timeline.
6. User inspects details; may perform follow-up actions.

**Alternative Flow 2a – Filter & Search**
1. User applies filters (Status, Priority, Date).
2. System returns filtered list; resume Step 2.

**Alternative Flow 5a – Download History**
1. User requests Export (PDF/CSV).
2. System generates file and offers download; resume Step 6.

**Exception Flow 2x – Ticket Not Found**
1. System cannot find ticket by ID/filter.
2. Displays guidance to adjust filters; returns to list.

**Exception Flow 3x – Database Error**
1. System reports inability to fetch data; user retries later.

**Business Rules**
- BR01: User can view only own tickets.
- BR02: Status limited to defined list.
- BR03: Timeline sorted by actual update time.
- BR04: Exports must satisfy security/compliance policies.

**Non-Functional Requirements**

| Category | Requirement | Target |
| --- | --- | --- |
| Performance | Fetch ticket detail | < 2 s |
| Security | Enforce creator-only access | 100% |
| Usability | Search & filter usability | Required |
| Availability | Status page uptime | 99.9% |

**Additional Information**
- Frequency: High; peak after submissions or during follow-ups.
- Priority: High.
- Related: Submit Ticket, Update Ticket Status.
- Dependencies: DB, Notification, Audit Log.
- Revision: 1.0 (11/08/2025).

---

### UC004 – Authentication

- **Version**: 1.0 (08/10/2025)
- **Actors**: End-User, Support Staff, Support Manager, System Admin
- **Description**: Secure login flow with MFA/OTP, remember-me, logout, and audit tracking.

**Pre-conditions**
1. User has active account.
2. System connected to identity stores/session infrastructure.

**Post-conditions**
- *Success*: Session/JWT created with role context, audit log recorded, user redirected to appropriate dashboard.
- *Failure*: Safe error message, failed-attempt counter increased, no session issued.

**Main Flow**
1. User opens Login page.
2. Enters email/password, selects “Sign in”.
3. System validates format.
4. Looks up user, verifies hashed password.
5. Checks account state (Active/Locked/etc).
6. Issues Session/JWT with roles/permissions.
7. Logs Login Success (time, userId, IP).
8. Redirects to role-based dashboard.

**Alternative Flow 2a – Remember Me**
1. User selects Remember Me.
2. System issues refresh token/long-lived cookie per policy.

**Alternative Flow 2b – Forgot Password**
1. User clicks Forgot Password.
2. System collects email, sends time-bound reset link.
3. User resets password under policy; returns to login.

**Exception Flow 4x – Invalid Credentials**
1. System increments failure counter, shows generic error.
2. Exceed threshold (≥5 attempts/15 min) → temporary lock.

**Exception Flow 5x – Account Disabled/Locked**
1. System displays account state guidance; denies login.

**Business Rules**
- BR01 Password ≥10 chars.
- BR02 Lockout ≥5 failures in 15 min (user+IP) → 15 min lock.
- BR03 Session security: inactivity timeout 30 min, refresh token ≤7 days, HttpOnly/SameSite/Secure cookies.
- BR04 Transport security: HTTPS + CSRF mitigation.
- BR05 Audit login/logout/failed/password-reset events.

**Non-Functional Requirements**

| Category | Requirement | Target |
| --- | --- | --- |
| Performance | Login response | < 2 s |
| Security | Post-login authorization accuracy | 100% |
| Usability | Safe, clear error messaging | Required |
| Availability | Auth service uptime | 99.9% |

**Dependencies**: User DB, Session Store/JWT service, Audit Log, Rate limiter.
- Revision: 1.0 (08/10/2025).

---

### UC005 – Dashboard

- **Version**: 1.0 (08/10/2025)
- **Actors**: All roles (views vary)
- **Description**: Role-aware dashboard with KPIs, graphs, SLA alerts, filters, and drill-down.

**Pre-conditions**
1. User authenticated with proper permissions.
2. Data sources (Ticket, Analytics, Cache) reachable.
3. SLA presets and saved layouts retrievable.

**Post-conditions**
- *Success*: Widgets and charts rendered per role, relevant audit logs recorded, optional user preferences saved.
- *Failure*: Show safe fallback widgets/messages; never expose unauthorized data.

**Main Flow**
1. User selects “Dashboard” (or redirected post-login).
2. System validates role/permissions.
3. Retrieves summary counts (status, priority, channel, owner/team).
4. Computes KPIs (FRT, resolution time, backlog, SLA % met, overdue).
5. Loads trend charts (Opened vs Resolved, heatmaps).
6. Displays widgets/cards/alerts for SLA risk/overdue.
7. Audit dashboard access/filter interactions.

**Exception Flow 3x – Data Source Timeout**
1. Detect timeout/unreachable source.
2. Show “Retry/Partial Data” widget with reload option.
3. Upon retry, re-run Step 3; log incident.

**Business Rules**
- BR01 Data freshness/caching (e.g., refresh every 5 minutes with TTL policy).
- BR02 SLA policy drives overdue/at-risk calculations.
- BR03 Rate limit refresh/export heavy queries.

**Non-Functional Requirements**

| Category | Requirement | Target |
| --- | --- | --- |
| Performance | Dashboard load | < 4 s |
| Security | TLS 1.2+ | 100% |
| Usability | Responsive layout | Required |
| Availability | Timeout/retry/fallback | Supported |

**Additional**: Frequency high; peak end-of-month; Priority High; Dependencies include Ticket Service, Analytics, User/Role Service, Cache, Audit Log, SLA Config.
- Revision: 1.0 (08/10/2025).

---

### UC006 – Assign/Update Ticket

- **Version**: 1.0 (08/10/2025)
- **Primary Actors**: Support Staff, Support Manager
- **Secondary Actor**: End-User (for reopen notifications)
- **Description**: Handle assignments, status transitions, SLA adjustments, notifications, and audit trails.

**Pre-conditions**
1. User authenticated with appropriate RBAC scope.
2. Ticket exists and is accessible (queue/team scope).
3. Ticket DB, Notification, SLA/Timer, and Audit services online.

**Post-conditions**
- *Success*: Assignment/status persisted, status history + audit log recorded, SLA timers adjusted, notifications sent.
- *Failure*: No data change; safe error returned.

**Main Flow**
1. User opens ticket from queue/list/detail (RBAC enforced).
2. Selects Assign/Update; system loads ticket and allowed actions.
3. User specifies action (assign, change status) with optional notes.
4. System validates required fields (assignee, reason, etc.).
5. Persist changes (assigned_to/status/notes).
6. Create status history + audit entries.
7. Adjust SLA timers (start/stop/pause per status).
8. Notify affected parties.
9. Update UI with confirmation.

**Alternative Flow 4b – Resolve**
1. Staff sets status to Resolved with resolution note/code.
2. System persists, stops resolution timer, notifies End-User; resume Step 6.

**Alternative Flow 4c – Close**
1. Manager/Staff closes ticket (must be Resolved + confirmation or auto-close).
2. System persists, shuts down SLAs, optionally send satisfaction survey; resume Step 6.

**Alternative Flow 4d – Reopen**
1. End-User requests reopen with reason (within policy window).
2. System transitions to In Progress/Open, notifies parties; resume Step 6.

**Exception Flow 3x – Permission Denied**
1. RBAC fails; system denies action, may increment security counters.

**Exception Flow 7x – Notification Failure**
1. Persist succeeds but notification fails → log error, queue retry, inform user of delayed notifications.

**Business Rules**
- BR01 Allowed transitions: Open → In Progress; In Progress ↔ On Hold; In Progress → Resolved; Resolved → Closed; Resolved/Closed → Reopen.
- BR02 Mandatory note for On Hold/Resolved/Closed/Reopen.
- BR03 Assign policy: Staff self-assign within team; cross-team reassignment requires Manager.
- BR04 Close policy: Only after Resolved and confirmation/elapsed period.
- BR05 SLA handling: On Hold pauses timers, In Progress runs, Resolved/Closed stops; track FRT/Resolution time accurately.
- BR06 Audit/history required on every assignment/status change.
- BR07 Reopen window limited (e.g., within 7 days of Close).

**Non-Functional Requirements**

| Category | Requirement | Target |
| --- | --- | --- |
| Performance | Persist assign/update | < 1.5 s |
| Security | RBAC/queue scope enforcement | 100% |
| Usability | Confirm modals + inline validation | Required |
| Availability | Assign/Update uptime | 99.9% |

**Additional**: Frequency high; peaks during intake; Priority high; Dependencies: Ticket DB, SLA/Timer, Notification, Audit Log, RBAC/Team Queue; Revision 1.0 (08/10/2025).

---

### UC007 – Comment/Conversation

- **Version**: 1.0 (08/10/2025)
- **Primary Actors**: End-User, Support Staff
- **Secondary Actors**: Support Manager, Notification Service, Attachment/Scanning Service, SLA/Timer Service, Audit Log
- **Description**: Real-time conversation on tickets, supporting public/internal notes, attachments, mentions, quoting, edit/delete windows, and SLA-aware triggers.

**Pre-conditions**
1. Authenticated user with access to ticket (RBAC/team/creator).
2. Ticket exists and allows comments per policy.
3. Connectivity to Ticket/Comment DB, Notification, Attachment/Scanning, SLA/Timer, Audit.

**Post-conditions**
- *Success*: Comment plus attachments saved, timeline refreshed, notifications issued, audit/history updated, SLA adjustments applied as needed.
- *Failure*: No data change, clear error shown; notification failures queued for retry.

**Main Flow**
1. User opens ticket detail (Conversation tab) → system loads latest conversation with RBAC filtering.
2. User chooses “Add Comment” → input field plus attachment controls displayed.
3. User enters text and attaches files (optional) → system validates.
4. System persists comment + attachments, associates with ticket/author.
5. Audit log and comment history entries created.
6. System sends notifications based on visibility rules.
7. UI updates timeline with new comment.

**Alternative Flow 4a – Edit Comment**
1. Author clicks Edit within allowed timeframe.
2. System verifies permissions/time window; user saves update.
3. System stores new version, marks comment as edited.

**Alternative Flow 4b – Delete Comment**
1. Author/Manager selects Delete and confirms within policy.
2. System removes comment content, displays placeholder, hides attachments, retains audit trail.

**Exception Flow 4x – Attachment Error**
1. File violates type/size or fails malware scan.
2. System rejects offending file, allows text-only submission; user may retry.

**Business Rules**
- BR01 Edit/Delete window (e.g., ≤10 minutes) unless elevated privileges.
- BR02 Attachment limits: ≤5 files/comment, ≤20 MB each, allowed types, must pass malware scan.
- BR03 Auto-SLA: End-User comment on On Hold ticket resumes work per policy.
- BR04 Ordering/timezone respect actual timestamps and user locale.
- BR05 Audit/history stored for create/edit/delete.

**Non-Functional Requirements**

| Category | Requirement | Target |
| --- | --- | --- |
| Performance | Create comment | < 1.5 s |
| Security | RBAC, visibility, PII masking | 100% |
| Availability | Conversation feature uptime | 99.9% |

**Additional**: Frequency high; peaks after status updates; Priority high; Dependencies: Ticket/Comment DB, Notification, Attachment/Scanning, SLA/Timer, Audit, RBAC; Revision 1.0 (08/10/2025).

---

## 6. Process & Interaction Diagrams (Narrative Summaries)

### Use Case Diagram
Illustrates interactions between End-User, Support Staff, Support Manager, and System Admin across features such as submitting tickets, viewing dashboards, managing users, defining SLA policies, managing integrations, and workflow automation. Includes extend/include relationships for shared services like notifications and search.

### Activity Diagrams
1. **Intake & Ticket Creation**: Covers initial capture, validation, persistence, and notification steps.
2. **Assignment (Swimlane)**: Shows coordination among roles (End-User, Support Staff, Support Manager) including queue handling and notification triggers.
3. **Resolution (Swimlane)**: Details troubleshooting, status transitions, SLA adjustments, and documentation.
4. **Communication & Tracking (Swimlane)**: Depicts comment exchanges, timeline updates, and SLA impacts.
5. **Ticket Closure**: Final confirmation, surveys, and audit logging for closure.

### Robustness Diagrams
- **UC001 Manage Users**: Actor (SystemAdmin), boundary (UsersPage/UserForm/UserDetailPanel), control objects (UserController, RoleAssignmentController, AuthzPolicyChecker, NotificationService), and entities (User, Role, UserRole, AuditLog).
- **UC002 Submit Ticket**: EndUser interacting with CreateTicketForm and AttachmentWidget, validated by ValidationService, orchestrated by TicketController, persisting Ticket/Attachment, logging to AuditLog, notifying stakeholders.
- **UC003 View Ticket Status + Export**: MyTicketsPage/FiltersBar/TicketDetailPanel with TicketQueryController and ExportController interacting with Ticket, Comment, TicketStatusHistory, AuditLog.

### Sequence Diagrams
1. **Manage Users**: System Admin → UserForm → UserController → UserEntity/AuditLog/Notification, returning results to UI.
2. **Submit Ticket**: EndUser → TicketForm → TicketController → TicketEntity (ID generation) → NotificationService → TicketForm result display.
3. **View Ticket Status**: EndUser → TicketList → TicketController → TicketEntity → TicketList response.

### State Machine Highlights
- **Ticket Entity**: Initial → Open → In Progress ↔ On Hold → Resolved → Closed (final) with Reopen transitions, audit logging and notifications at each step.
- **User Account**: Initial → Creating → Validating → Active → (Deactivated ↔ Active) → Deleted (final), with Reset Password and guard conditions (e.g., not last admin).
- **Notification Entity**: Triggered → Queued → Sending → Sent → Delivered/Failed/Cancelled with retry loops and audit entries.

## 7. Domain & Data Models

### Manage Users Domain Model
Entities: `User`, `Role`, `Permission`, join tables `UserRole`, `RolePermission`, plus supporting `PasswordResetToken`, `AuditLog`. Supports CRUD, role binding, password reset, and traceability.

### Ticket Submission & Status Models
- `Ticket` (fields: ticketId, subject, description, priority, categoryId, status, isDraft, createdAt, updatedAt, closedAt).
- Relationships: User (1→* tickets), Category (*→1), Attachment (1→*), StatusHistory (1→*), Notification (1→*), AuditLog (1→*), Comment (for View Ticket Status).

### Comment Visibility
`Comment` links to Ticket and User with visibility flags (public/internal) and histories for edits/deletes, ensuring End-Users only view authorized content.

## 8. Prototype / UI Notes

- **Manage Users**: Admin landing page lists users with Edit/Delete/Create actions; Create form captures user data before returning to list.
- **Submit Ticket via Portal**: End-User home with “Create New Ticket” modal/form including fields and attachments; post-submit detail view for confirmation.
- **View Ticket Status**: “My Ticket” list with detail view, timeline tab, and conversation tab showing Admin ↔ End-User communication logs.

## 9. Appendix

- **Additional Related Use Cases**: Assign Roles & Permissions, Login/SSO, Manage Teams/Groups, View Ticket Status, Update/Assign Ticket, Dashboard, Reports/Export, Workflow Automation.
- **Operational Considerations**: Audit logging for every critical action, SLA-driven alerts, caching strategy for dashboards, retry queues for notification failures, malware scanning integration for attachments.

This README now serves as the consolidated requirements artifact for the IT Support Ticket System and should be kept in sync with future revisions to business processes, UX prototypes, and architectural diagrams.

---

## Docker Development Environment

The repository now ships with a self-contained Docker stack (`Dockerfile`, `docker-compose.yml`, and configs under `docker/`). It provisions:

- **app** – PHP 8.2 FPM + Composer + Node 20 (serves Laravel and builds Vite assets).
- **web** – Nginx front-end proxying `app` and serving `public/`.
- **mysql** – MySQL 8 with persisted volume (`mysql_data`).
- **redis** – Redis 7 for cache/session/queue workloads.
- **queue** – Dedicated worker running `php artisan queue:work`.

### 1. One-time setup

1. Copy environment config: `cp .env.example .env`.
2. Update these keys for the container network:
   ```
   APP_URL=http://localhost:8080
   DB_HOST=mysql
   DB_PORT=3306
   DB_PASSWORD=secret          # matches docker-compose default
   REDIS_HOST=redis
   QUEUE_CONNECTION=database
   SESSION_DRIVER=database
   CACHE_STORE=database
   ```
3. Stop or re-map any host-level MySQL/Redis already bound to ports 3306/6379.

### 2. Build and start the stack

```bash
docker compose build
docker compose up -d
```

The custom entrypoint will automatically install Composer/npm dependencies (stored in named volumes), build Vite assets, generate `APP_KEY`, run pending migrations, link storage, and clear caches once MySQL/Redis are reachable. The UI becomes available at `http://localhost:8080`.

### 3. Common workflows

| Task | Command |
| --- | --- |
| Tail logs | `docker compose logs -f app web queue` |
| Run Artisan | `docker compose exec app php artisan <command>` |
| Run migrations | `docker compose exec app php artisan migrate --force` |
| Tinker shell | `docker compose exec app php artisan tinker` |
| Composer install/update | `docker compose exec app composer install` / `composer update` |
| Vite dev server | `docker compose exec app npm run dev -- --host 0.0.0.0 --port 5173` (expose port as needed) |
| Run tests | `docker compose exec app php artisan test` |

### phpMyAdmin

- Service auto-starts with the stack (`phpmyadmin` container) and exposes `http://localhost:8081`.
- Default credentials mirror the `.env` DB settings; with the provided sample values use user `root` and password `secret`.
- Any changes to `DB_USERNAME` / `DB_PASSWORD` must be reflected in `.env` before running `docker compose up` so phpMyAdmin can authenticate against MySQL.

### 4. Stopping, rebuilding, cleaning

```bash
docker compose down            # stop containers
docker compose down -v         # stop and remove volumes (wipes MySQL data)
docker compose up -d --build   # rebuild after Dockerfile or dependency changes
```

To force migrations to run again, delete `storage/app/.migrated` before starting the stack.
