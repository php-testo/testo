<?php

declare(strict_types=1);

namespace Testo\Output\Terminal\Renderer;

/**
 * ANSI color codes for terminal styling.
 */
enum Color: string
{
    case Green = "\033[32m";
    case Red = "\033[31m";
    case Yellow = "\033[33m";
    case Blue = "\033[34m";
    case Cyan = "\033[36m";
    case Gray = "\033[90m";
    case White = "\033[97m";

    // Styles
    case Reset = "\033[0m";
    case Bold = "\033[1m";
    case Dim = "\033[2m";

    // Backgrounds
    case BgRed = "\033[41m";
    case BgGreen = "\033[42m";
}
