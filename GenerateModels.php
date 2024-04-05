<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenerateModels extends Command
{
    protected $signature = 'generate:models';
    protected $description = 'Generate Eloquent models for all tables in all databases';

    public function handle() {
        $databases = $this->getDatabases();

        foreach ($databases as $databaseName) {
            $this->info("Generating models for database: $databaseName");
            Config::set('database.connections.mysql.database', $databaseName);
            DB::purge('mysql');
            DB::reconnect('mysql');

            try {
                // Make sure to pass a string query directly
                $tables = DB::select('SHOW TABLES');
                foreach ($tables as $key => $table) {
                    $tables[$key] = reset($table);
                }

                foreach ($tables as $tableName) {
                    $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $tableName)));

                    // Generate the Eloquent model file for the table
                    $this->generateEloquentModel($tableName, $className, $databaseName);
                }

                $this->info('Eloquent models generated successfully for '.$databaseName);
            } catch (\Exception $e) {
                $this->error("An error occurred in $databaseName: ".$e->getMessage());
            }
        }
    }

    function getDatabases() {
        $databases = DB::select('SHOW DATABASES');

        return array_map(function ($db) {
            return $db->Database;
        }, $databases);
    }

    function generateEloquentModel($tableName, $className, $databaseName) {
        // Fetch column names for the table
        $columns = Schema::getColumnListing($tableName);
        $primaryKey = $this->getPrimaryKey($tableName);

        $fillableArrayString = "protected \$fillable = ['".implode("', '", $columns)."'];\n";
        $primaryKeyString = "protected \$primaryKey = '$primaryKey';\n";

        $modelStr
            = "<?php\n\n// Author: Austen Green\n\nnamespace App\Models\\$databaseName;\n\nuse Illuminate\Database\Eloquent\Model;\n\n";
        $modelStr .= "class $className extends Model {\n";
        $modelStr .= "    protected \$table = '$tableName';\n";
        $modelStr .= $primaryKeyString;
        $modelStr .= "    $fillableArrayString";
        $modelStr .= "}\n";

        $directoryPath = app_path("Models/$databaseName");
        if ( ! file_exists($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        $filePath = "$directoryPath/$className.php";
        file_put_contents($filePath, $modelStr);
        $this->info("Model generated for $tableName: $filePath");
    }

    function getPrimaryKey($tableName) {
        $keys = DB::select('SHOW KEYS FROM `'.$tableName.'` WHERE Key_name = "PRIMARY"');

        return $keys[0]->Column_name ?? 'id'; // Assuming 'id' as default if no PK is found
    }


//    function getPrimaryKey($tableName) {
//        $keys = DB::select(DB::raw('SHOW KEYS FROM ' . $tableName . ' WHERE Key_name = "PRIMARY"'));
//        return $keys[0]->Column_name ?? 'id'; // Assuming 'id' as default if no PK is found
//    }
}


