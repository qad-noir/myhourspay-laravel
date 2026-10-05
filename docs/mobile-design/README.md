# MHP mobile companion concepts

Generated using the built-in image-generation tool. These are proposed visual
references for the Flutter implementation, not screenshots of a finished app.

- `core-screens.png`: sign-in, weekly overview, add hours, submit timesheet.
- `account-review-screens.png`: MFA, workspace selection, manager review, account/device sessions.

Use MHP's existing palette: warm white #fbf8f3, dark ink #171421, orange #ff6b35,
with Manrope/DM Sans-inspired type hierarchy, generous spacing and native controls.
The implementation must use actual brand assets, accessible contrast, touch targets,
text scaling and platform conventions rather than tracing every generated pixel.

Implementation notes:
- Sign-in provider buttons depend on GET /auth/providers and platform configuration.
- Timesheets and project controls depend on workspace features/roles.
- The first board's generated notification copy must be changed to "Your manager
  can review this week after you submit." Native push covers missing-entry reminders
  only; timesheet push notifications are not implemented.
- "Request changes" maps to the rejected decision with an explanatory review note.
- MFA status is read-only in this API version; do not make its row navigate to a
  nonexistent native MFA-management page. Authentication challenges do work.
- Total minutes and timesheet states come from Laravel, not fabricated demo data.
- Draft, loading, validation, conflict and offline states still need Flutter layouts.

Final image prompts are recorded in prompts.txt for reproducibility and revision.
