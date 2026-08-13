<?php

declare(strict_types=1);

namespace Testo\Bridge\VCR;

/**
 * A request attribute that must line up between a recorded interaction and an outgoing request for
 * PHP-VCR to replay the recording.
 *
 * Matchers are transport-agnostic: PHP-VCR normalizes every library hook (`stream_wrapper`, `curl`,
 * `soap`) into one `\VCR\Request`, and matchers compare fields of that object — so the same set works
 * regardless of which transport made the call. Two matchers are keyed to the request *shape* rather
 * than the transport: {@see self::PostFields} (form-urlencoded bodies) and {@see self::SoapOperation}
 * (the SOAP-ENV envelope). Pick the ones that fit the request.
 *
 * The backing value is the exact matcher name PHP-VCR's `Configuration::enableRequestMatchers()`
 * expects.
 *
 * @api
 */
enum Matcher: string
{
    case Method = 'method';

    /** Full request URL, including path and query string. PHP-VCR has no separate "path" matcher. */
    case Url = 'url';

    case Host = 'host';
    case QueryString = 'query_string';

    /** Raw request body — the general choice for JSON/XML/SOAP payloads. */
    case Body = 'body';

    /** Parsed `application/x-www-form-urlencoded` fields; only meaningful for form POSTs. */
    case PostFields = 'post_fields';

    case Headers = 'headers';

    /** SOAP operation parsed from the SOAP-ENV envelope; only meaningful for SOAP calls. */
    case SoapOperation = 'soap_operation';
}
