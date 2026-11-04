# Update — Required step photos

*Package 16 · UP16-S1 · Schema **1.27.0**. Operator request, settled 2026-11-02.*

---

## 1. What this adds

A workflow step can require pictures. Mark done stays locked until that step’s current progress has at least one JPEG or PNG. **Unblock step** is the bypass. It already skips every gate, writes the audit columns and the order note, and does not complete the step.

The files belong to the order’s run of the step, not to the template. Make progress is per order line. Pack and Post-ship progress is per order.

## 2. Schema

`SOM_DB::DB_VERSION` becomes **1.27.0**. Put the new columns on the existing `som_workflow_steps` CREATE and add the new table to the same `dbDelta` list `maybe_upgrade()` already runs.

`som_workflow_steps` gains:

| Column | Type | Meaning |
|---|---|---|
| `photo_required` | `tinyint(1) NOT NULL DEFAULT 0` | 1 = this step needs at least one photo before Mark done. |
| `photo_label` | `varchar(191) NULL` | Optional caption, such as “Photo of the laminated sheet”. Empty stores null. |

New table `{$wpdb->prefix}som_step_photos`:

| Column | Type |
|---|---|
| `id` | `bigint(20) unsigned` PK |
| `order_step_progress_id` | `bigint(20) unsigned NULL` — Pack and Post-ship |
| `order_item_step_progress_id` | `bigint(20) unsigned NULL` — Make |
| `attachment_id` | `bigint(20) unsigned NOT NULL` |
| `uploaded_by` | `bigint(20) unsigned NULL` |
| `created_at` | `datetime NOT NULL` |

Keys: primary `id`, and indexes on `order_step_progress_id`, `order_item_step_progress_id`, and `attachment_id`. Exactly one of the two progress ids is set. The writer enforces that. Existing steps stay `photo_required` 0, so an upgrade does not start blocking open orders.

## 3. Step editor

In `admin/views/workflow-step-editor.php`, add a gate next to the others:

- Checkbox **Require photo**.
- Text input for the label, maxlength 191. The input is there when the box is checked. A blank label is allowed.

`SOM_Workflows::normalize_step_rows()` stores the flag and the label (`sanitize_text_field`). A label longer than 191 characters fails the save with the usual step error and writes nothing. Saving a photo step sets `requires_manual_confirm` to 1, the same way a confirmation or a copy command already forces manual confirm.

A batch step cannot also require a photo. `normalize_step_rows()` rejects that combination, and the editor warning lists photo with the other gates a batch step cannot keep. Photo may sit with manual confirm, a confirmation checklist, a copy command, and a timer. The zero-gate warning treats **Require photo** as a gate, in PHP and in `stepHasGate()` in `admin/assets/js/admin.js`.

`SOM_Workflows::step_gates_label()` includes **photo** when the flag is set. `step_to_submission_row()` and `insert_cloned_steps()` copy `photo_required` and `photo_label`. Clone already runs the same save checks, so a batch-plus-photo source still fails the clone and leaves no partial template.

`enter_step()` and `SOM_Item_Make::enter_item_step()` treat `photo_required` as manual. A photo-only step enters `in_progress`. It does not take the zero-gate auto-complete path.

## 4. Files

New class `SOM_Step_Photos` in `includes/class-som-step-photos.php`.

| Rule | Value |
|---|---|
| Types | `image/jpeg` and `image/png` only. Check with `wp_check_filetype_and_ext`, then the attachment’s stored mime, the same order as `SOM_Shipments` proof. A PDF is rejected. |
| Size | 5 MB (`5242880`). A larger file is rejected and not stored. |
| WordPress | `media_handle_upload()`, parent post `0`. |
| Folder | While that one upload runs, filter `upload_dir` so the file lands in `wp-content/uploads/order-machine/step-photos/YYYY/MM/`. Remove the filter before returning. Other uploads in the same request keep the normal folder. |
| Name | WordPress’s sanitized file name. A collision gets the usual `-1` suffix. |
| Row | Insert `som_step_photos` with the current progress id, the attachment id, the current user, and `created_at`. |
| Failed mime after insert | `wp_delete_attachment( $id, true )` and return `WP_Error`. No photo row. |
| Remove | Delete the photo row and `wp_delete_attachment( $id, true )`. |

The attachment stays visible in the Media library as unattached. The order screen is where the operator uses it. There is no private download proxy.

Add and remove are allowed only when all of these are true: the user can `manage_options`, the progress row is the current step, the step has `photo_required`, and the progress status is not `pending` or `done`. A step that does not require a photo has no uploader. Unblock does not remove that permission: the operator can still attach a picture after unblocking, and can still Mark done without one.

## 5. Order detail

On the current step, in the Make line, the Pack panel, and the Post-ship panel, show the label when it is set, otherwise the heading **Photo**. Then the pictures already saved for that progress row, each with a thumbnail (`wp_get_attachment_image`) linking to the file URL, and a **Remove** control. Then a file input (`accept="image/jpeg,image/png"`) and **Add photo**.

One form posts `som_step_photo` with nonce `som_step_photo`. Make posts the order item id, matching the other make forms. Pack and Post-ship post the order id and use `order_step_progress`. The handler is in the existing order-detail POST switch in `admin/class-som-admin-menu.php`.

A finished step that has photos shows the thumbnails and hides Add and Remove. A finished step with none shows nothing extra.

Mark done uses `SOM_Workflow_Engine::can_mark_done()`. That method returns false when `photo_required` is set, the progress has no `som_step_photos` row, and `manual_unblock_at` is empty. Make already calls `can_mark_done()` before it completes a line, and the boards already use it for drag, so a missing photo blocks those too. The board does not grow an uploader.

`blocking_gate_keys()` adds `photo` in that same case. **Unblock step** then appears, because today it appears only when a gate key is present (or the step is waiting or in error). The confirm copy stays the sentence already on the button. The order note stays the existing sentence; the gate list includes `photo`.

Removing the last photo locks Mark done again, unless the step was already unblocked.

## 6. Import and abilities

`SOM_Workflow_Import::COLUMNS` gains `photo_required` and `photo_label` at the end. Export writes them. Import uses `SOM_Catalog_Import::parse_bool` for the flag; a missing or blank cell is 0. A blank label is null. An old workflow file that omits the two columns still imports, with photos not required. A file that sets the flag on a batch step fails the existing save and creates no template.

`SOM_Abilities::map_workflow_step()` adds `photo_required` (0 or 1) and `photo_label` (string or null). No ability returns attachment ids, file URLs, or the photo rows. No write ability.

## 7. Out of this sprint

- Named slots (front and back), a required count above one, a camera capture attribute, or a phone upload flow.
- A photo-only skip that would leave a checklist or a timer still blocking. Unblock continues to skip every gate.
- An uploader on Make, Pack, or Post-ship boards. Unblock on boards stays deferred.
- A per-product photo requirement. Product instruction and task overrides stay as they are.
- Changing shipment proof. It stays JPEG, PNG, or PDF, one file, on the shipment row.
- Deleting photos when an order is deleted, and hiding the attachments from the Media library.
- Budget and board REST, the Product Costing packaging line, Pack Copy UI, the Trays overview page, hard-delete of script dispatch classes, and a customer order that contains only internal products.

## 8. Done when

A step saved with **Require photo** and a label keeps both. A batch step with that box checked does not save. Entering a photo-only step leaves it `in_progress`. Mark done stays off until one JPEG or PNG is stored for that progress row, and comes back on after **Unblock step** with `photo` in `manual_unblock_gates`. A Make line and the order’s Pack step keep separate pictures. A done step still shows its thumbnails and has no file input. Clone and a workflow CSV round-trip keep the flag and the label.
