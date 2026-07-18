<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\User;
use App\Services\AffiliateCommissionService;
use App\Services\AffiliateFraudService;
use App\Services\PlayerBonusService;
use App\Services\VelocityService;
use App\Support\BrazilCities;
use App\Support\Cpf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class RegisterController extends Controller
{
    public function store(
        Request $request,
        PlayerBonusService $playerBonus,
        AffiliateCommissionService $affiliates,
        AffiliateFraudService $fraud,
        VelocityService $velocity,
    ): RedirectResponse {
        $cpfDigits = Cpf::digits((string) $request->input('cpf', ''));
        $email = strtolower(trim((string) $request->input('email', '')));
        $uf = strtoupper(trim((string) $request->input('estado', '')));
        $ip = $request->ip();

        try {
            $velocity->assertRegisterAllowed($ip);
        } catch (RuntimeException $e) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => $e->getMessage()]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'cpf' => ['required', 'string', 'max:14'],
            'sexo' => ['required', 'in:masculino,feminino,outro,nao_informar'],
            'data_nascimento' => ['required', 'date', 'before:'.now()->subYears(18)->toDateString()],
            'estado' => ['required', 'string', 'size:2', Rule::in(array_keys(BrazilCities::allByUf() ?: $this->fallbackUfs()))],
            'cidade' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'affiliate_code' => ['nullable', 'string', 'max:40'],
        ], [
            'name.required' => 'Informe seu nome.',
            'cpf.required' => 'Informe seu CPF.',
            'sexo.required' => 'Selecione o sexo.',
            'data_nascimento.required' => 'Informe a data de nascimento.',
            'data_nascimento.before' => 'É necessário ter 18 anos ou mais.',
            'estado.required' => 'Selecione o estado.',
            'cidade.required' => 'Selecione a cidade.',
            'email.required' => 'Informe seu e-mail.',
            'password.confirmed' => 'A confirmação de senha não confere.',
        ]);

        if (! Cpf::isValid($cpfDigits)) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['cpf' => 'CPF inválido.']);
        }

        if (User::query()->where('cpf', $cpfDigits)->exists()) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['cpf' => 'Este CPF já está cadastrado.']);
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Este e-mail já está cadastrado.']);
        }

        $city = BrazilCities::normalizeCityName($data['cidade'], $uf);
        if ($city === null) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['cidade' => 'Selecione uma cidade válida para o estado informado.']);
        }

        $affiliateId = null;
        $rawAffiliateCode = trim((string) ($data['affiliate_code'] ?? ''));
        if ($rawAffiliateCode !== '') {
            $affiliate = $affiliates->resolveAffiliateByCode($rawAffiliateCode);
            if (! $affiliate) {
                return back()
                    ->withInput($request->except('password', 'password_confirmation'))
                    ->withErrors(['affiliate_code' => 'Código de afiliado inválido ou inativo.']);
            }

            try {
                $fraud->assertCanLink($affiliate, $cpfDigits, $ip);
            } catch (RuntimeException $e) {
                return back()
                    ->withInput($request->except('password', 'password_confirmation'))
                    ->withErrors(['affiliate_code' => $e->getMessage()]);
            }

            $affiliateId = (int) $affiliate->id;
        }

        try {
            $user = DB::transaction(function () use ($data, $cpfDigits, $email, $uf, $city, $playerBonus, $affiliateId, $ip) {
                $user = User::query()->create([
                    'name' => $data['name'],
                    'cpf' => $cpfDigits,
                    'sexo' => $data['sexo'],
                    'data_nascimento' => $data['data_nascimento'],
                    'estado' => $uf,
                    'cidade' => $city,
                    'email' => $email,
                    'password' => $data['password'],
                    'balance' => 0,
                    'bonus_balance' => 0,
                    'affiliate_id' => $affiliateId,
                    'registration_ip' => $ip,
                    'last_ip' => $ip,
                ]);

                if ($affiliateId) {
                    $ownerId = Affiliate::query()->whereKey($affiliateId)->value('user_id');
                    if ((int) $ownerId === (int) $user->id) {
                        $user->affiliate_id = null;
                        $user->save();
                    }
                }

                // Novo jogador no cadastro; bônus de afiliado só no 1º depósito.
                $playerBonus->applyNewPlayerBonus($user);

                return $user->fresh();
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'CPF ou e-mail já cadastrado.']);
        }

        $velocity->hitRegister($ip);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('games.show', 'tigre-aviator');
    }

    /**
     * @return list<string>
     */
    protected function fallbackUfs(): array
    {
        return [
            'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG',
            'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
        ];
    }
}
