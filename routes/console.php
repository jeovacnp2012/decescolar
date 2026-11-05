<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Reenvia ao chamados o aviso "atendido" que falhou (o responsável já baixou o PDF mas o chamados estava fora do ar).
Schedule::command('atestados:avisar-atendidos')->everyTenMinutes()->withoutOverlapping();
