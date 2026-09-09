<?php

namespace App\Search;

interface SearchProviderInterface
{
    /** @return LiveResult[] */
    public function search(string $query): array;
}
