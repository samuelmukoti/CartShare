
We are building a Wordpress plugin called MyCartShare... Following is a draft PRD


# Product Requirements Document: Save & Share Cart App

## 1. Document Overview

**Product Name:** Save & Share Cart  
**Product Type:** Ecommerce cart-sharing plugin/app  
**Reference Category:** WooCommerce cart enhancement  
**Primary Users:** Online shoppers, ecommerce store owners, store administrators  
**Document Version:** 1.0  
**Prepared For:** Product planning and software development  

---

## 2. Product Summary

Save & Share Cart is an ecommerce feature that allows shoppers to save their current shopping cart, generate a unique shareable link, and restore that cart later or send it to another person for review, approval, or payment.

The product is designed for ecommerce stores where customers may want to:

- Save a cart for later.
- Share a cart with a friend, family member, manager, or purchasing department.
- Send a cart to someone else who will complete the purchase.
- Create repeat-purchase carts.
- Print or email cart contents.
- Access saved carts from their customer account.

For store owners, the product helps reduce cart abandonment, support B2B purchasing workflows, improve checkout completion, and make carts more portable across users and devices.

---

## 3. Problem Statement

Most ecommerce carts are temporary and tied to a browser session or customer account. If a shopper leaves, changes device, clears cookies, or wants someone else to review the order, the cart can easily be lost.

This creates several problems:

- Customers abandon carts because they are not ready to purchase immediately.
- Shoppers cannot easily send a full cart to someone else.
- B2B buyers struggle to share purchase selections with managers or procurement teams.
- Returning customers have to rebuild repeat carts manually.
- Store owners lose conversion opportunities when carts are not preserved.

---

## 4. Product Goals

The product should:

1. Allow customers to save their current cart.
2. Generate secure, unique cart restore links.
3. Allow carts to be shared through email, social media, direct copy link, and print.
4. Allow registered customers to manage saved carts from their account.
5. Allow store admins to configure sharing options, labels, email templates, styling, and expiration rules.
6. Support both guest and registered users.
7. Restore cart contents accurately, including product variations and quantities.
8. Provide a foundation for future B2B, analytics, and cart-template workflows.

---

## 5. Non-Goals

The initial version does not need to:

- Store payment information.
- Store billing or shipping addresses.
- Replace a full quote-management system.
- Guarantee compatibility with every third-party product add-on plugin.
- Provide full abandoned-cart email automation.
- Provide procurement approvals in the MVP.
- Include multi-store marketplace functionality in the first release.

---

## 6. Target Users

### 6.1 Guest Shopper

A guest shopper is not logged in but can still create and share a cart.

Guest shoppers should be able to:

- Add products to cart.
- Generate a shareable cart link.
- Copy the cart link.
- Email the cart link.
- Share the cart through supported social platforms.
- Print cart contents.
- Open a shared cart link and restore the cart.

Guest cart ownership should be based on secure tokens and session data.

---

### 6.2 Registered Customer

A registered customer has an account with the ecommerce store.

Registered customers should be able to:

- Save carts with custom names.
- Save multiple carts if enabled.
- View saved carts in their account.
- Restore saved carts.
- Replace an existing saved cart.
- Delete saved carts.
- Share saved carts with others.

---

### 6.3 Store Administrator

A store administrator manages the ecommerce store and plugin settings.

Admins should be able to:

- Enable or disable the feature.
- Configure sharing methods.
- Customize frontend labels.
- Customize popup styling.
- Configure email template design.
- Configure print-cart content.
- View saved-cart history.
- Delete saved carts.
- Configure cart-link expiration and cleanup rules.

---

## 7. Primary Use Cases

### 7.1 Save Cart for Later

A shopper adds products to their cart, clicks **Save / Share Cart**, gives the cart a name, and saves it. Later, they return to their account and restore the saved cart.

### 7.2 Share Cart With Another Person

A shopper creates a cart, opens the share popup, selects a sharing method, and sends the cart link to someone else.

Supported sharing methods should include:

- Email
- Copy link
- Print
- Facebook
- Messenger
- WhatsApp
- X / Twitter
- LinkedIn
- Skype

### 7.3 Send Cart for Payment

A shopper sends a cart link to another person who opens the cart, reviews the contents, and completes checkout.

### 7.4 Restore Cart From Link

A recipient opens a shared cart link. The system validates the link, restores the cart, and redirects the user to the cart or checkout page.

### 7.5 Manage Saved Carts

A registered customer logs into their account, opens the **Saved Carts** section, and restores or deletes a saved cart.

### 7.6 Admin Reviews Saved Cart History

An admin opens the saved-cart history screen, reviews saved carts, checks cart contents, and deletes old or unwanted records.

---

## 8. User Stories

### 8.1 Shopper Stories

#### Save Cart

As a shopper, I want to save my cart so that I can return later without rebuilding it.

**Acceptance Criteria**

- The shopper can save a non-empty cart.
- The system generates a unique cart link.
- The cart can be restored later.
- Empty carts cannot be saved.

#### Share Cart

As a shopper, I want to share my cart with someone else so that they can review or purchase the items.

**Acceptance Criteria**

- The shopper can copy a share link.
- The shopper can email the cart link.
- The shopper can share through enabled social platforms.
- The recipient can restore the cart from the shared link.

#### Print Cart

As a shopper, I want to print my cart so that I can keep a physical copy or share it offline.

**Acceptance Criteria**

- The print view displays product names, quantities, prices, subtotals, and cart total.
- Admin-configured header and footer content appear in the print view.
- The print layout is readable.

---

### 8.2 Registered Customer Stories

#### View Saved Carts

As a registered customer, I want to view my saved carts from my account so that I can restore or delete them.

**Acceptance Criteria**

- The customer account area includes a **Saved Carts** section.
- Saved carts display name, date, item count, total, and actions.
- The customer can restore or delete their own saved carts.

#### Replace Saved Cart

As a customer, I want to replace a saved cart with my current cart so that I can update a saved shopping list.

**Acceptance Criteria**

- The customer can replace only carts they own.
- The system asks for confirmation before replacement.
- The saved cart is updated with the current cart contents.
- The system optionally clears the active cart after replacement if configured.

---

### 8.3 Admin Stories

#### Configure Sharing Options

As an admin, I want to choose which sharing methods are available so that I can control the customer experience.

**Acceptance Criteria**

- Admin can enable or disable each sharing method.
- Disabled sharing methods are hidden from the frontend popup.
- Settings are saved and applied immediately.

#### Configure Cart Expiration

As an admin, I want to automatically delete old saved carts so that the database does not grow indefinitely.

**Acceptance Criteria**

- Admin can enable or disable automatic cleanup.
- Admin can configure expiration duration in days, weeks, or months.
- Expired carts are deleted or marked expired by a scheduled job.

#### Customize Labels and Styling

As an admin, I want to customize popup labels, messages, colors, and email branding so that the feature matches my store.

**Acceptance Criteria**

- Admin can update labels for buttons, messages, and form fields.
- Admin can update popup colors.
- Admin can customize email template branding.
- Frontend reflects the configured settings.

---

## 9. Functional Requirements

## 9.1 Save Cart

The system shall allow a shopper to save the current cart.

### Requirements

- The cart must contain at least one item.
- The system must serialize cart contents.
- The system must store product ID, variation ID, quantity, selected attributes, coupons, and metadata.
- The system must generate a secure unique token.
- Registered users may assign a cart name.
- Guest users may generate a shareable cart link without creating an account.
- Admin may configure whether the active cart is cleared after saving.

### Edge Cases

- Product is deleted after cart is saved.
- Product becomes out of stock.
- Variation is no longer available.
- Coupon expires.
- Price changes.
- Cart contains third-party product add-on metadata.

---

## 9.2 Generate Share Link

The system shall generate a unique restore URL for each saved cart.

### Requirements

- Link must use a high-entropy token.
- Link must not expose internal cart IDs directly.
- Link must optionally expire.
- Link must work for both guest and logged-in recipients.
- Admin can choose redirect destination after restore:
  - Cart page
  - Checkout page

### Acceptance Criteria

- Valid link restores the cart.
- Invalid link shows a friendly error.
- Expired link cannot restore the cart.
- Deleted cart cannot be restored.

---

## 9.3 Restore Cart

The system shall restore a saved cart when a user opens a valid shared cart link.

### Restore Flow

1. User opens shared cart URL.
2. System validates token.
3. System checks cart status.
4. System checks expiration.
5. System loads cart data.
6. System validates products and variations.
7. System clears or merges with current cart based on configuration.
8. System adds valid items to the active cart.
9. System applies valid coupons.
10. System displays warnings for unavailable items.
11. System redirects user to cart or checkout.

### Requirements

- Restore process must not expose personal information.
- Restore process must handle missing products gracefully.
- Restore process must show notices for skipped items.
- Restore process should log a restore event.

---

## 9.4 Share Popup

The system shall display a popup/modal for saving and sharing carts.

### Popup Sections

- Save cart section
- Share options section
- Email form section
- Confirmation/success messages
- Error messages

### Supported Actions

- Save cart
- Continue to share options
- Copy link
- Email cart
- Print cart
- Share to enabled social platforms
- Go back to previous popup step
- Replace existing cart if enabled

---

## 9.5 Email Cart Sharing

The system shall allow users to email a saved cart link.

### User Inputs

- Recipient email address
- Email subject
- Message body

### Requirements

- Validate recipient email.
- Generate or reuse saved cart link.
- Send branded HTML email.
- Include a call-to-action button to open the cart.
- Log email sharing event.

### Email Template Settings

Admin should be able to configure:

- Logo
- Email header name
- Default subject
- Header background color
- Header text color
- Body background color
- Footer background color
- Footer text color
- Button background color
- Button text color
- Template border color
- Button label
- Header text
- Footer text

---

## 9.6 Social Sharing

The system shall allow cart links to be shared through supported social platforms.

### Supported Platforms

- Facebook
- Messenger
- WhatsApp
- X / Twitter
- LinkedIn
- Skype

### Requirements

- Admin can enable or disable each platform.
- Share URLs must include the cart restore link.
- Share text should be configurable.
- Disabled platforms should not appear in the popup.

---

## 9.7 Copy Link

The system shall allow users to copy the cart link to their clipboard.

### Requirements

- Copy action must use the generated cart link.
- System should show a success message after copying.
- System should provide fallback behavior if clipboard API is unavailable.

---

## 9.8 Print Cart

The system shall allow users to print cart contents.

### Requirements

- Print view must include cart items, quantities, prices, subtotal, and total.
- Admin can add content before the cart.
- Admin can add content after the cart.
- Admin can add custom print CSS.
- Print view should exclude unnecessary site navigation.

---

## 9.9 Saved Cart History

The system shall maintain saved-cart history.

### Customer Account View

Registered customers should see:

- Cart name
- Created date
- Last updated date
- Item count
- Cart total
- Version column, if enabled
- Restore action
- Delete action

### Admin View

Admins should see:

- Cart ID
- Cart title
- Customer
- Guest/session identifier
- Created date
- Last accessed date
- Expiration date
- Status
- Token
- Item count
- Cart total
- Actions

Admin actions:

- View cart
- Delete cart
- Copy link
- Mark expired
- Restore for testing

---

## 9.10 Multiple Cart Handling

The system shall support single-cart and multi-cart modes.

### Multi-Cart Mode

Customers can save multiple carts.

### Single-Cart Mode

Customers are limited to one saved cart and may update or replace that cart.

### Admin Settings

- Enable multiple saved carts.
- Disable multiple cart links.
- Show or hide replace button.
- Show or hide cart version column.
- Flush cart on save.
- Flush cart on replace.

---

## 9.11 Cart Expiration and Cleanup

The system shall support scheduled cleanup of old carts.

### Requirements

- Admin can enable or disable cleanup.
- Admin can configure cleanup interval using:
  - Days
  - Weeks
  - Months
- Scheduled job should run automatically.
- Expired carts should be deleted or marked expired.
- Expired links should not restore carts.

---

## 10. Admin Settings

## 10.1 General Settings

| Setting | Type | Description |
|---|---|---|
| Enable module | Boolean | Turns feature on or off |
| Redirect destination | Select | Cart or checkout |
| Enable multiple carts | Boolean | Allows customers to save multiple carts |
| Show cart version column | Boolean | Displays version column in saved carts |
| Enable replace button | Boolean | Allows replacing saved carts |
| Flush cart on save | Boolean | Clears active cart after saving |
| Flush cart on replace | Boolean | Clears active cart after replacing |

---

## 10.2 Sharing Settings

| Setting | Type | Description |
|---|---|---|
| Enable email sharing | Boolean | Allows email sharing |
| Enable Facebook sharing | Boolean | Allows Facebook sharing |
| Enable Messenger sharing | Boolean | Allows Messenger sharing |
| Enable WhatsApp sharing | Boolean | Allows WhatsApp sharing |
| Enable X / Twitter sharing | Boolean | Allows X/Twitter sharing |
| Enable LinkedIn sharing | Boolean | Allows LinkedIn sharing |
| Enable Skype sharing | Boolean | Allows Skype sharing |
| Enable print cart | Boolean | Allows cart printing |
| Enable copy link | Boolean | Allows direct link copy |
| Enable save cart | Boolean | Allows saving cart for later |

---

## 10.3 Popup Styling Settings

| Setting | Type |
|---|---|
| Popup header background color | Color |
| Popup header text color | Color |
| Popup body background color | Color |
| Popup footer background color | Color |
| Popup footer text color | Color |
| Popup icon text color | Color |
| Popup loader color | Color |
| Popup overlay color | Color |
| Popup overlay opacity | Number |

---

## 10.4 Label Settings

Admin should be able to customize:

- Popup title
- Share cart button label
- Save cart button label
- Continue button label
- Back button label
- Replace cart label
- Copy link label
- Print cart label
- Email label
- Facebook label
- Messenger label
- WhatsApp label
- X / Twitter label
- LinkedIn label
- Skype label
- Email recipient placeholder
- Email subject placeholder
- Email message placeholder
- Send email button label
- Success messages
- Validation messages
- Error messages

---

## 10.5 Print Settings

| Setting | Type | Description |
|---|---|---|
| Enable print | Boolean | Enables print cart |
| Content before cart | Rich text | Appears above printed cart |
| Content after cart | Rich text | Appears below printed cart |
| Custom print CSS | Textarea | Custom CSS for print layout |

---

## 10.6 Cleanup Settings

| Setting | Type | Description |
|---|---|---|
| Enable cleanup | Boolean | Enables automatic cleanup |
| Duration type | Select | Days, weeks, or months |
| Duration value | Number | Number of units before expiration |
| Cleanup behavior | Select | Delete or mark expired |

---

## 11. Frontend Screens and Components

## 11.1 Cart Page Button

A **Save / Share Cart** button should appear on the cart page.

Possible placements:

- Near checkout button
- Below cart totals
- Inside cart actions area
- Floating action area
- Mini-cart drawer

---

## 11.2 Save Cart Popup

Fields and actions:

- Cart name input
- Save button
- Continue button
- Replace button, if enabled
- Error and success messages

---

## 11.3 Share Options Popup

Displays enabled sharing methods as buttons or icons.

Actions:

- Copy link
- Email
- Print
- Social share
- Back

---

## 11.4 Email Share Form

Fields:

- Recipient email
- Subject
- Message

Actions:

- Send email
- Back
- Cancel

---

## 11.5 My Account: Saved Carts

A registered customer account page should include a **Saved Carts** tab or section.

Table columns:

- Cart name
- Created date
- Last updated date
- Item count
- Cart total
- Version, optional
- Restore
- Delete

---

## 11.6 Admin: Saved Cart History

Admin table columns:

- Cart ID
- Cart name
- Customer
- Guest/session identifier
- Created date
- Last accessed date
- Expiration date
- Status
- Item count
- Total
- Actions

---

## 12. Data Model

## 12.1 saved_carts

| Field | Type | Required | Notes |
|---|---|---:|---|
| id | bigint | Yes | Primary key |
| user_id | bigint | No | Registered user owner |
| guest_id | string | No | Guest/session identifier |
| title | string | No | Cart name |
| token | string | Yes | Public restore token |
| edit_token | string | No | Secure token for guest edits |
| cart_hash | string | No | Used to detect duplicate cart versions |
| cart_data | json | Yes | Serialized cart contents |
| cart_total | decimal | No | Saved total at time of save |
| currency | string | Yes | Store currency |
| item_count | integer | Yes | Number of cart items |
| redirect_to | string | Yes | cart or checkout |
| status | enum | Yes | active, expired, deleted |
| expires_at | datetime | No | Expiration date |
| created_at | datetime | Yes | Created timestamp |
| updated_at | datetime | Yes | Updated timestamp |
| last_accessed_at | datetime | No | Last restore/view time |

---

## 12.2 saved_cart_items

This table is optional but recommended for reporting and analytics.

| Field | Type | Required | Notes |
|---|---|---:|---|
| id | bigint | Yes | Primary key |
| saved_cart_id | bigint | Yes | Parent saved cart |
| product_id | bigint | Yes | Product ID |
| variation_id | bigint | No | Variation ID |
| quantity | integer | Yes | Quantity |
| product_name_snapshot | string | No | Product name at save time |
| price_snapshot | decimal | No | Price at save time |
| attributes | json | No | Selected variation attributes |
| metadata | json | No | Add-ons and custom metadata |

---

## 12.3 saved_cart_events

Used for analytics and audit history.

| Field | Type | Required | Notes |
|---|---|---:|---|
| id | bigint | Yes | Primary key |
| saved_cart_id | bigint | Yes | Parent saved cart |
| event_type | string | Yes | Event name |
| user_id | bigint | No | Acting user |
| ip_address | string | No | Optional |
| user_agent | text | No | Optional |
| metadata | json | No | Event metadata |
| created_at | datetime | Yes | Timestamp |

### Event Types

- created
- shared_email
- copied_link
- shared_social
- printed
- restored
- replaced
- deleted
- expired
- checko
