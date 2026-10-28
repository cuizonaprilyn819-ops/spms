# SPMS page colors

Preserve the existing deep blue sidebar: `linear-gradient(165deg, #090C2D, #14194F)`. The user requested that it remain after the UI refresh. Typography, spacing, and responsive navigation remain separate from this color choice.

Use locally hosted Figtree for the application interface, with normal and italic faces and variable weights from 300 to 900. The shared `workspace-font` component and Tailwind sans family apply it to the sign-in page and authenticated pages.

| Use | Color |
| --- | --- |
| Primary buttons, links, committed quantities | `#34508E` |
| Primary hover | `#283E72` |
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
