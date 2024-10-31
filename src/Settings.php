<?php

declare(strict_types=1);

namespace Core\Service;

use Northrook\ArrayStore;

final class Settings extends ArrayStore
{
    /**
     * @param string $storageDirectory
     */
    public function __construct(
        string $storageDirectory,
    ) {
        parent::__construct( $storageDirectory, $this::class );
    }
}
