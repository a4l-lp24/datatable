<?php

namespace DataTable;

use Illuminate\Support\ServiceProvider;

class DataTableServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->configure();
    }

    protected function configure(): void {
        $this->mergeConfigFrom(__DIR__ . '/../config/datatable.php', 'datatable');
    }
}