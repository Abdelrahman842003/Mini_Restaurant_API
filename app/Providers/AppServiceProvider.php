<?php

namespace App\Providers;

use App\Http\Interfaces\InvoiceRepositoryInterface;
use App\Http\Interfaces\MenuItemRepositoryInterface;
use App\Http\Interfaces\OrderRepositoryInterface;
use App\Http\Interfaces\PaymentGatewayInterface;
use App\Http\Interfaces\ReservationRepositoryInterface;
use App\Http\Interfaces\TableRepositoryInterface;
use App\Http\Interfaces\UserRepositoryInterface;
use App\Http\Interfaces\WaitingListRepositoryInterface;
use App\Http\Repositories\InvoiceRepository;
use App\Http\Repositories\MenuItemRepository;
use App\Http\Repositories\OrderRepository;
use App\Http\Repositories\ReservationRepository;
use App\Http\Repositories\TableRepository;
use App\Http\Repositories\UserRepository;
use App\Http\Repositories\WaitingListRepository;
use App\Http\Services\PaymentGateways\PaypalPaymentService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind repository interfaces to their implementations
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(MenuItemRepositoryInterface::class, MenuItemRepository::class);
        $this->app->bind(TableRepositoryInterface::class, TableRepository::class);
        $this->app->bind(ReservationRepositoryInterface::class, ReservationRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(InvoiceRepositoryInterface::class, InvoiceRepository::class);
        $this->app->bind(WaitingListRepositoryInterface::class, WaitingListRepository::class);

        // Bind payment gateway interface to PayPal implementation
        $this->app->bind(PaymentGatewayInterface::class, PaypalPaymentService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
