<?php

namespace App\Enums;

/**
 * Bounded category taxonomy for the Resource Library (files) — same shape
 * and nullable-on-the-model convention as LinkDepartment.
 */
enum TeamResourceCategory: string
{
    case PluginsSoftware = 'plugins_software';
    case CertificatesCompliance = 'certificates_compliance';
    case TemplatesDocuments = 'templates_documents';
    case PoliciesSops = 'policies_sops';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PluginsSoftware => 'Plugins & Software',
            self::CertificatesCompliance => 'Certificates & Compliance',
            self::TemplatesDocuments => 'Templates & Documents',
            self::PoliciesSops => 'Policies & SOPs',
            self::Other => 'Other',
        };
    }
}
