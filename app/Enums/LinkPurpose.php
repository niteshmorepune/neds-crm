<?php

namespace App\Enums;

/**
 * Bounded purpose taxonomy for Important Links (what kind of link it is) —
 * nullable on the model, not a case here, for the same reason as
 * LinkDepartment: "no purpose set yet" is a real state, not a category.
 */
enum LinkPurpose: string
{
    case ClientPortal = 'client_portal';
    case VendorLogin = 'vendor_login';
    case InternalDocs = 'internal_docs';
    case Reference = 'reference';

    public function label(): string
    {
        return match ($this) {
            self::ClientPortal => 'Client Portals',
            self::VendorLogin => 'Vendor/Tool Logins',
            self::InternalDocs => 'Internal Docs',
            self::Reference => 'Reference',
        };
    }
}
