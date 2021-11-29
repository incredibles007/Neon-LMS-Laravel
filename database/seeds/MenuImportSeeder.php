<?php

use Illuminate\Database\Seeder;

class MenuImportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $menus = \Harimayco\Menu\Models\Menus::all();
        foreach ($menus as $menu) {
            if ($menu != NULL) {
                $menuItems = \Harimayco\Menu\Models\MenuItems::where('menu', '=', $menu->id)->get();
                if ($menuItems != null) {
                    $allMenu = [];
                    foreach ($menuItems as $item) {
                        if($item->parent == 0){
                            $item->parent = $item->id;
                            $item->save();
                        }
                        $allMenu[str_slug($item['label'])] = $item['label'];
                    }
                    $main[str_slug($menu->name)] = $allMenu;
                    $file = fopen(public_path('../resources/lang/en/custom-menu.php'), 'a');
                    if ($file !== false) {
                        ftruncate($file, 0);
                    }
                    fwrite($file, '<?php return ' . var_export($main, true) . ';');

                    Artisan::call('menu:import');
                }
            }
        }

    }
}
