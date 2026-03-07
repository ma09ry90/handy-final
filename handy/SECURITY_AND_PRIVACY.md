Security & Privacy Best Practices for User Management
===================================================

1. Passwords
- Store passwords using a strong adaptive hashing algorithm (Argon2id or bcrypt).
- Use the framework's hashing helpers and never store plain text.

2. Documents & Files
- Store uploaded documents in encrypted object storage (S3 with SSE, or encrypted volumes).
- Never store documents as base64 blobs in the database; store references in `documents` table.
- Generate presigned URLs for temporary download access; restrict access to admins only.

3. Sensitive PII
- Encrypt sensitive columns (national IDs, bank account numbers) at rest using application-level encryption or DB encryption.
- When displaying, mask values (e.g., show last 4 digits only).

4. Access Control & Auditing
- Use role-based access control; restrict profile approval endpoints to admin roles.
- Record immutable audit logs for all approval/rejection actions with actor id and timestamp (`profile_audit_logs`).

5. Secure Upload Pipeline
- Validate MIME types and file extensions; enforce size limits.
- Run server-side virus/malware scanning on uploaded files before making them available to reviewers.

6. Data Retention & Deletion
- Define retention policies for documents and PII; support secure deletion and data export for users.

7. Transport & Secrets
- Enforce HTTPS and HSTS.
- Store secrets (API keys, DB credentials) in environment variables or a secrets manager.

8. Admin Safety
- Enforce MFA for admin accounts and strong password rotation policies.

9. Background Checks & Third Parties
- Store only reference IDs and minimal metadata from third-party reports; avoid storing full reports unless required.

10. Logging & Monitoring
- Avoid logging raw PII. Mask or redact sensitive fields in logs.
- Monitor for suspicious admin activity and rate-limit sensitive endpoints.

11. Testing & CI
- Include tests for role enforcement, validation rules, and audit logging.
- In CI, use ephemeral databases and ensure secrets are not leaked in logs.

For implementation details see the migrations and models under `database/migrations` and `app/Models`.
