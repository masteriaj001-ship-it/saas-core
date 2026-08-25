<?php

declare(strict_types=1);

namespace App\Modules\Plataforma\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Plataforma\Models\ImpersonationLog;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;

class ImpersonationService
{
    private const SESSION_KEY = 'impersonating_tenant_id';

    public function start(User $superadmin, Tenant $tenant): ImpersonationLog
    {
        $this->stopIfActive($superadmin);

        $log = ImpersonationLog::create([
            'superadmin_id' => $superadmin->id,
            'tenant_id' => $tenant->id,
            'started_at' => now(),
            'ip_address' => Request::ip(),
        ]);

        Session::put(self::SESSION_KEY, $tenant->id);

        return $log;
    }

    public function stop(User $superadmin): void
    {
        $tenantId = Session::get(self::SESSION_KEY);

        if ($tenantId) {
            ImpersonationLog::where('superadmin_id', $superadmin->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);

            Session::forget(self::SESSION_KEY);
        }
    }

    public function isImpersonating(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    public function getImpersonatingTenantId(): ?string
    {
        return Session::get(self::SESSION_KEY);
    }

    private function stopIfActive(User $superadmin): void
    {
        if ($this->isImpersonating()) {
            $this->stop($superadmin);
        }
    }
}
