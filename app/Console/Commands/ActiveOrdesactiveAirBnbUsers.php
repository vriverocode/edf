<?php

namespace App\Console\Commands;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Console\Command;

class ActiveOrdesactiveAirBnbUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:active-ordesactive-air-bnb-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enable or disable users based on activate_time or  end_time columns in DB';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::where('rol_id', Rol::AIRBNB)->get();
        foreach ($users as $user) {
            if ($user->active_time >= date('Y-m-d')) {
                $user->status = 1;
            }
            if ($user->end_time < date('Y-m-d')) {
                $user->status = 3;
            }

            $user->save();
        }
    }
}
