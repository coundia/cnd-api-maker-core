<?php

declare(strict_types=1);

namespace CndApiMaker\Core\Definition;

final class StorageDefinition
{
    public ?string $table = null;
    public array $fillable = [];
    public array $hidden = [];
    public array $casts = [];
}
