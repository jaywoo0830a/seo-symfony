<?php

declare(strict_types=1);

namespace App\Util;

use Doctrine\DBAL\Exception as DbalException;

final class DbError
{
    /**
     * Strips PostgreSQL framing so the user sees just the trigger / constraint
     * message — e.g. "Publish gate: content_node 4 requires data_count >= 5 (have 0)".
     */
    public static function summarise(DbalException $e): string
    {
        $msg = $e->getPrevious()?->getMessage() ?? $e->getMessage();
        if (preg_match('/ERROR:\s+(.*?)(?:\n|CONTEXT|$)/s', $msg, $m)) {
            return trim($m[1]);
        }

        return $msg;
    }
}
