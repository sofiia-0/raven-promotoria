<div class="admin-stack">
    <header class="admin-page-heading"><div><p class="access-eyebrow">Cuentas y accesos</p><h1>Administración</h1><p>Gestione las cuentas que tienen acceso a Raven.</p></div><x-access.motif compact class="admin-heading-motif" /></header>
    <section class="admin-metrics" aria-label="Resumen de cuentas">
        <article class="access-card admin-metric"><div><p>Usuarios registrados</p><x-access.icon name="users" /></div><strong>{{ $total }}</strong><span>Cuentas en el sistema</span></article>
        <article class="access-card admin-metric"><div><p>Cuentas activas</p><x-access.icon name="check" /></div><strong>{{ $active }}</strong><span>Con acceso habilitado</span></article>
        <article class="access-card admin-metric"><div><p>Cuentas inactivas</p><x-access.icon name="inactive" /></div><strong>{{ $inactive }}</strong><span>Con acceso desactivado</span></article>
    </section>
    <div class="admin-dashboard-grid">
        <section class="access-card admin-users-feature"><span class="admin-feature-icon"><x-access.icon name="users" /></span><p class="access-eyebrow">Directorio del sistema</p><h2>Gestión de usuarios</h2><p>Consulte y actualice las cuentas de Coordinadora y Promotora, su rol y su estado de acceso.</p><a href="{{ route('admin.users.index') }}" class="raven-button raven-button-primary">Gestionar usuarios<x-access.icon name="arrow" /></a><div class="admin-feature-foot"><x-access.icon name="lock" /><span>Las nuevas cuentas configuran su contraseña mediante un enlace de acceso.</span></div></section>
        <section class="access-card admin-roles"><div class="admin-section-title"><x-access.icon name="identity" /><h2>Distribución por rol</h2></div><dl>@foreach(['admin'=>'Administración','coordinator'=>'Coordinadora','promoter'=>'Promotora'] as $key=>$label)<div><dt><span class="admin-role-dot admin-role-dot--{{ $key }}" aria-hidden="true"></span>{{ $label }}</dt><dd>{{ $roles[$key] ?? 0 }}</dd></div>@endforeach</dl><p class="access-help">Incluye cuentas activas e inactivas.</p></section>
    </div>
</div>
