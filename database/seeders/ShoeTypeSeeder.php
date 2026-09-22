<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ShoeTypeSeeder extends Seeder
{
    /**
     * ShoeType is a physical table concept (card shoe = deck holder for live dealer tables).
     * Not applicable to Virtuals tab-based games — seeder intentionally left empty.
     * Migration kept for reference / revert purposes.
     */
    public function run(): void
    {
        // No-op: shoe types are not used in the Virtuals system.
    }
}
