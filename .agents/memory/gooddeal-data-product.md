---
name: Gooddeal Data product requirements
description: User-stated customer audience and payment-provider secrecy constraints for the Gabon data-bundle shop.
---

Most Gooddeal Data customers use mobile, so prioritize a simple, readable mobile-first purchase flow. The Gabon checkout must offer Airtel Money and Moov Money only. Customer-facing screens must not reveal which payment aggregator processes transactions. See the project README for the PHP, SQLite/MySQL, and Hostinger constraints.

**Why:** The user explicitly set these audience and privacy requirements for this product.

**How to apply:** Preserve these constraints when changing customer pages, checkout, or payment messaging; derive the enabled wallet options from the Gabon/XAF service catalogue.
