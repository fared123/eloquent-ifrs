<?php

namespace Tests\Unit;

use IFRS\Exceptions\InvalidAccountType;
use IFRS\Exceptions\MissingVatAccount;
use IFRS\Models\Account;
use IFRS\Models\Currency;
use IFRS\Models\Entity;
use IFRS\Tests\TestCase;

use IFRS\Models\RecycledObject;
use IFRS\User;
use IFRS\Models\Vat;

class VatTest extends TestCase
{
    /**
     * Test Vat model Entity Scope.
     *
     * @return void
     */
    public function testVatEntityScope()
    {
        $newEntity = factory(Entity::class)->create();
        $newEntity->currency_id = factory(Currency::class)->create()->id;

        $user = factory(User::class)->create();
        $user->entity()->associate($newEntity);
        $user->save();

        $this->be($user);

        $vat = new Vat([
            'name' => $this->faker->name,
            'code' => $this->faker->randomLetter(),
            'rate' => 10,
            'account_id' => factory(Account::class)->create([
                'account_type' => Account::CONTROL,
                'category_id' => null
            ])->id,
        ]);
        $vat->attributes();
        $vat->save();

        $this->assertEquals(
            $vat->toString(true),
            'Vat: ' . $vat->name . ' (' . $vat->code . ') at ' . number_format($vat->rate, 2) . '%'
        );
        $this->assertEquals(
            $vat->toString(),
            $vat->name . ' (' . $vat->code . ') at ' . number_format($vat->rate, 2) . '%'
        );
        $this->assertEquals(count(Vat::all()), 1);

        $this->be(User::withoutGlobalScopes()->find(1));
        $this->assertEquals(count(Vat::all()), 0);
    }

    /**
     * Test Vat Model recylcling
     *
     * @return void
     */
    public function testVatRecycling()
    {
        $vat = new Vat([
            'name' => $this->faker->name,
            'code' => $this->faker->randomLetter(),
            'rate' => 10,
            'account_id' => factory(Account::class)->create([
                'category_id' => null
            ])->id,
        ]);
        $vat->delete();

        $recycled = RecycledObject::all()->first();
        $this->assertEquals($vat->recycled->first(), $recycled);
    }

    /**
     * Test Missing Vat Account.
     *
     * @return void
     */
    public function testMissingVatAccount()
    {
        $vat = new Vat([
            'name' => $this->faker->name,
            'code' => $this->faker->randomLetter(),
            'rate' => 10,
        ]);
        $this->expectException(MissingVatAccount::class);
        $this->expectExceptionMessage($vat->rate . '% VAT requires a Vat Account');

        $vat->save();
    }

    /**
     * Test Invalid Vat Account Type.
     *
     * @return void
     */
    public function testInvalidVatAccountType()
    {
        $vat = new Vat([
            'name' => $this->faker->name,
            'code' => $this->faker->randomLetter(),
            'rate' => 10,
            'account_id' => factory(Account::class)->create([
                'account_type' => Account::RECEIVABLE,
                'category_id' => null
            ])->id,
        ]);
        $this->expectException(InvalidAccountType::class);
        $this->expectExceptionMessage('Vat Account must be of Type Control ');

        $vat->save();
    }
}
