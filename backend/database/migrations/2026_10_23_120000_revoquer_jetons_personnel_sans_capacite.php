<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les jetons du personnel étaient créés sans capacité explicite, donc avec
 * « * », et franchissaient les routes réservées aux étudiants. Ils portent
 * désormais la capacité « admin » ; ceux déjà émis restaient valables jusqu'à
 * leur expiration. Les révoquer force une nouvelle connexion, qui délivre un
 * jeton conforme. Les jetons d'étudiant (capacité « etudiant ») ne sont pas touchés.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('personal_access_tokens')
            ->where('tokenable_type', (new User)->getMorphClass())
            ->where('abilities', '["*"]')
            ->delete();
    }

    public function down(): void
    {
        // Une révocation ne se défait pas : les utilisateurs se reconnectent.
    }
};
