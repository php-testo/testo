<?php

declare(strict_types=1);

namespace Testo\Pipeline\Attribute;

/**
 * An {@see Interceptable} attribute whose case-level interceptors are wired even when the attribute
 * is placed on a test method or function, not only on the case class.
 *
 * By default a test-level attribute reaches only the per-test pipeline
 * ({@see \Testo\Pipeline\Middleware\TestRunInterceptor}); the case pipeline
 * ({@see \Testo\Pipeline\Middleware\TestCaseRunInterceptor}) is built from class attributes alone.
 * Implement this interface when the attribute has to act on the whole case from a single test —
 * e.g. to take that test out of the case before any lifecycle hook runs. The interceptor is
 * instantiated once per attribute occurrence; use {@see InterceptorOptions::$onConflict} to
 * collapse the duplicates when several tests carry the attribute.
 *
 * @api
 */
interface CaseInterceptable extends Interceptable {}
