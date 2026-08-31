# Support / Refund Form

Backend and database work for the customer support/refund (DOA) form — verifying a customer's
email via OTP, looking up their orders, and submitting a refund ticket with photo evidence.

## What I Developed

- Created `support.class.php` for all support/refund database operations.
- Created the `submit_ticket.api.php` endpoint for real ticket submission, and updated the existing
  OTP endpoints to support it.
- Implemented the OTP email verification flow, including fixing silent email-send failures.
- Implemented email verification and order lookup against the `orders` table.
- Implemented retrieval of a customer's orders after successful OTP verification.
- Implemented refund/support ticket submission (replacing the previous fake, client-only submission).
- Implemented database storage for submitted tickets.
- Implemented customer file/photo uploads.
- Implemented storage of uploaded file metadata in `ticket_files`.
- Added validation for required form fields and SKU selection.
- Added protection against double/triple clicking the "Send Verification Code" button.
- Fixed the Send Verification Code button state when the customer changes their email.
- Reset the form back to the initial email stage after successful submission.
- Added and used the `tickets` and `ticket_files` database tables.
- Added local configuration/session settings needed to run the feature locally.
- Added dummy/test order data for local testing.

## Database

### `tickets`
Stores one row per submitted refund/DOA request — customer/email/order details, DOA count,
requested resolution, description, and status (`pending` / `approved` / `denied`).

### `ticket_files`
Stores metadata for each uploaded photo (original filename, stored filename, MIME type, size),
linked to its ticket via `ticket_id` (foreign key to `tickets.ticket_id`, `ON DELETE CASCADE`).

Both tables include `created_at` and `modified_at` timestamp columns.

## Support Form Flow

```
Email
 → Send OTP
 → Verify OTP
 → Retrieve orders
 → Complete refund form
 → Upload photos
 → Submit request
 → Save ticket and files
 → Success/reset
```

## Files Added/Modified

- `public/includes/support.class.php` — created
- `public/support/submit_ticket.api.php` — created
- `public/support/otp_email.api.php` — modified (report failure when the email fails to send)
- `public/support/index.php` — modified (real submission, SKU handling, button-state fixes, form reset)
- `public/style/support.css` — modified (styling for the new submission error message)
- `public/includes/config.inc.php` — created (local session config)
- `public/includes/dbh.inc.php` — created (local database connection)
- `database/tickets_schema.sql` — created (`tickets` + `ticket_files` tables)
- `database/seed_test_order.sql` — created (dummy order for local testing)

## Testing

- Tested OTP verification.
- Tested customer order lookup.
- Tested refund form validation.
- Tested ticket submission.
- Tested file uploads.
- Verified records are inserted into `tickets`.
- Verified uploaded file records are inserted into `ticket_files`.
- Tested changing email and resetting the verification flow.
- Tested successful submission resets the form.

## Notes

- Local configuration (`config.inc.php`, `dbh.inc.php`) is for development only.
- No production credentials are committed anywhere in this project.
- Uploaded files are stored on disk under `public/uploads/tickets/{ticket_id}/`, separate from
  their metadata in `ticket_files`.