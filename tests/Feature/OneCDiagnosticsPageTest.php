<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OneCDiagnosticsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_open_one_c_diagnostics_page(): void
    {
        config()->set('integrations.one_c.username', 'site-exchange');
        config()->set('integrations.one_c.password', 'secret-1c');

        $manager = User::factory()->create([
            'is_manager' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager)->get(route('manager.onec.show'));

        $response->assertOk();
        $response->assertSee('Диагностика 1С');
        $response->assertSee(route('onec.exchange'));
        $response->assertSee('site-exchange');
    }

    public function test_regular_user_cannot_open_one_c_diagnostics_page(): void
    {
        $user = User::factory()->create([
            'is_manager' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('manager.onec.show'))
            ->assertForbidden();
    }

    public function test_manager_can_import_catalog_from_received_one_c_package(): void
    {
        $manager = User::factory()->create([
            'is_manager' => true,
            'is_active' => true,
        ]);

        $sessionKey = 'session-catalog-test';
        $basePath = 'one-c-exchange/'.$sessionKey.'/catalog';

        Storage::disk('local')->put($basePath.'/import.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация ВерсияСхемы="2.10" ДатаФормирования="2026-04-07T11:00:00">
  <Классификатор>
    <Группы>
      <Группа>
        <Ид>group-root</Ид>
        <Наименование>Профиля</Наименование>
      </Группа>
    </Группы>
  </Классификатор>
  <Каталог>
    <Товары>
      <Товар>
        <Ид>product-guid-1</Ид>
        <Артикул>PF-001</Артикул>
        <Наименование>Профиль M</Наименование>
        <Группы>
          <Ид>group-root</Ид>
        </Группы>
      </Товар>
    </Товары>
  </Каталог>
</КоммерческаяИнформация>
XML);

        Storage::disk('local')->put($basePath.'/offers.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация ВерсияСхемы="2.10" ДатаФормирования="2026-04-07T11:01:00">
  <ПакетПредложений>
    <ТипыЦен>
      <ТипЦены>
        <Ид>price-type-1</Ид>
        <Наименование>Цена 1</Наименование>
      </ТипЦены>
    </ТипыЦен>
    <Предложения>
      <Предложение>
        <Ид>product-guid-1</Ид>
        <Цены>
          <Цена>
            <ИдТипаЦены>price-type-1</ИдТипаЦены>
            <ЦенаЗаЕдиницу>530.00</ЦенаЗаЕдиницу>
          </Цена>
        </Цены>
      </Предложение>
    </Предложения>
  </ПакетПредложений>
</КоммерческаяИнформация>
XML);

        $this->actingAs($manager)
            ->post(route('manager.onec.catalog.import'), [
                'session_key' => $sessionKey,
            ])
            ->assertRedirect(route('manager.onec.show'));

        $this->assertDatabaseHas('categories', [
            'one_c_id' => 'group-root',
            'name' => 'Профиля',
        ]);

        $this->assertDatabaseHas('products', [
            'one_c_id' => 'product-guid-1',
            'title' => 'Профиль M',
            'vendor_code' => 'PF-001',
        ]);

        $product = Product::query()->where('one_c_id', 'product-guid-1')->firstOrFail();

        $this->assertSame(530.0, (float) $product->price_from);
    }
}
