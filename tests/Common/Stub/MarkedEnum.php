<?php

declare(strict_types=1);

namespace Tests\Common\Stub;

enum MarkedEnum: string
{
    #[CaseMarkerAttribute('first')]
    case Marked = 'marked';
    case Plain = 'plain';

    #[CaseMarkerAttribute('second')]
    #[CaseMarkerAttribute('third')]
    case Repeated = 'repeated';
}
