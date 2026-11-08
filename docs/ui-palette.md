# SPMS page colors

Preserve the existing deep blue sidebar: `linear-gradient(165deg, #090C2D, #14194F)`. The user requested that it remain after the UI refresh. Typography, spacing, and responsive navigation remain separate from this color choice.

Use locally hosted Figtree for the application interface, with normal and italic faces and variable weights from 300 to 900. The shared `workspace-font` component and Tailwind sans family apply it to the sign-in page and authenticated pages.

| Use | Color |
| --- | --- |
| Links, committed quantities, SLSU emphasis | `#34508E` |
| Link hover | `#283E72` |
| Soft blue panels and selected page sections | `#EAF1FB` |
| Page background | `#F3F7FC` |
| Content surfaces | `#FFFFFF` |
| Main text | `#1E2A4A` |
| Borders | `#DCE5F0` |
| Sidebar active indicator | `#71C8E7` |
| Keyboard focus | `#0369A1` |
| Reported quantities | `#075985` on `#EDF7FC` |
| Validated quantities | `#166534` on `#EEF8F3` |
| Unallocated responsibility | `#92400E` on `#FFF8EB` |
| Errors / rejection | `#B42318`, with a readable pale red surface |

Use the shared `spms` Tailwind colors and the workspace variables in `resources/css/app.css`. The Tailwind blue scale is coordinated with the primary color so existing blue utilities fit the same palette. Keep body surfaces white or lightly tinted; reserve saturated colors for actions, selected states, small chart marks, and meaningful status. Labels must explain states without relying on color alone. Keep exports readable independently of the web palette.

## Sneat action palette

The approved workspace refresh adds Sneat-inspired button colors while preserving SLSU branding. Keep action tokens separate from the `--workspace-*` colors: violet actions must not recolor the sidebar, report measurements, or exported forms.

| Action | Background | Text / icon |
| --- | --- | --- |
| Primary: create, save, submit | `#5F61E6` (hover `#5052C9`) | White |
| Accent reference | `#696CFF` | Use for small accents, not small white text |
| Approval / validation | `#71DD37` | `#244715` |
| Information / files | `#DEF8FE`, hover `#03C3EC` | `#07627A`, hover `#083F4B` |
| Caution / return | `#FFAB00` | `#513600` |
| Delete / reject | `#FF3E1D` | `#351007` |
| Secondary | `#F1F4F9` or white | `#50617A` |

The primary tone is slightly darker than Sneat's accent so normal white button text remains readable. Bright success, warning, and danger buttons use dark labels. Meaning always remains in the text, accessible name, and icon; colors do not grant permission or change workflow decisions.

## Shared semantic components

Sneat colors also apply to messages, badges, selected tabs, dropdown items, summary accents and progress indicators through `resources/css/semantic-ui.css`. Use `semantic-alert`, `semantic-badge` and `semantic-progress` Blade components, or the shared `spms-alert`, `spms-badge` and `data-tone` attributes on retained markup.

| Tone | Soft surface | Readable foreground | Meaning |
| --- | --- | --- | --- |
| Primary | `#EAE8FF` | `#484AB5` | Drafts, selections, main actions |
| Success | `#E8F9DC` | `#2D6418` | Approved commitments, validated work, successful actions |
| Warning | `#FFF2D1` | `#805100` | Pending review, returned drafts, unallocated work |
| Danger | `#FFE2DC` | `#AD2812` | Errors, rejection, destructive actions |
| Info | `#D8F6FD` | `#07627A` | Information and reported work |
| Neutral | `#EDF0F3` | `#50617A` | Secondary or inactive states |

Badges support `soft`, `solid` and `outline` variants and optional pills. Saturated fills use readable contrasting labels. Progress is static unless `busy` explicitly describes active processing; reduced-motion disables its animation. Keep reported and validated values labeled separately. No colors imply approval or change ratings.

## Action buttons

Recognizable form and page actions show both an icon and a visible text label on desktop and mobile, with a minimum 44 px touch target. Users should not need to hover to identify Save, Delete, Upload or Submit. Action rows wrap where needed, and long labels wrap within the available width. Each control retains its accessible name and original form behavior. Motion runs briefly on interaction and respects reduced-motion preferences.

Navigation, account/menu triggers, field selectors, sign-in controls, and content links keep their existing presentation; the mobile navigation toggle includes “Menu”. The progressive enhancement in `resources/js/action-buttons.js` uses recognizable action labels, follows API-rendered content and changing busy labels, and falls back to the original text buttons if JavaScript is unavailable. Use `data-action-plain` to preserve a custom button's presentation. A deliberate compact desktop control may use `data-action-icon-only`; it retains an accessible name and a hover/focus label, and shows its text on touch screens. Ordinary actions must not opt into this compact mode.

## Page structure

- Shared floating top bar, grouped role-aware navigation, breadcrumb, compact page heading, white panels, and a quiet footer. Locally hosted Figtree applies to authenticated pages, authentication, and recovery screens.
- The **Current period** indicator shows the latest open period. It is a read-only reference, not a global filter; report-specific dates remain visible in each record.
- Report, historical-PCR, and profile tabs retain normal anchor destinations without JavaScript. With JavaScript, keyboard arrow/Home/End navigation selects the associated panel. A validation error opens its containing panel.
- Simple tables become labeled records on mobile. Institutional matrices with merged headings retain horizontal scrolling and their column relationships. Original documents and generated institutional exports retain their established layouts.
- `data-workspace-table` adds an optional **Search visible rows** toolbar. It filters only the loaded page and says so explicitly. Existing server-side directory filters and pagination remain authoritative.
- AI assistance, human validation, commitment approval, employee agreement, and final performance rating retain their separate responsibilities.
