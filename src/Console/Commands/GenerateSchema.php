<?php

namespace Appoly\SmartSchema2\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\VarExporter\VarExporter;

class GenerateSchema extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'smartschema:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $schemas = [];
        $tables = DB::select('SHOW TABLES');
        foreach ($tables as $table) {
            foreach ($table as $key => $value) {
                $modelClassName = ucfirst(Str::camel($value));
                if (substr($modelClassName, -1) === 's') {
                    $modelClassName = substr($modelClassName, 0, -1);
                }

                if (!class_exists("App\\$modelClassName")) {
                    $this->info("{$modelClassName} hasn't got a class!");
                    continue;
                }

                $this->info("Found {$modelClassName}!");

                $columns = DB::select(DB::raw('SHOW COLUMNS FROM `' . $value . '`'));
                $columnsArray = [];
                foreach ($columns as $column) {
                    $columnsArray[] = [
                        'name' => $column->Field,
                        'type' => $column->Type,
                        'null' => $column->Null
                    ];
                }

                $schema = $this->makeSchema($columnsArray);

                if (!empty($schema['items'])) {
                    $txt =
                        '<?php

namespace App\\SmartSchema;

class ' . $modelClassName . 'Schema
{
    const SCHEMA = ' . VarExporter::export($schema) . ';
}
?>';

                    if (!is_dir(app_path() . '/SmartSchema')) {
                        // dir doesn't exist, make it
                        mkdir(app_path() . '/SmartSchema');
                    }

                    $filename = app_path() . "/SmartSchema/" . $modelClassName . "Schema.php";
                    file_put_contents($filename, $txt);
                }
            }
        }
    }

    public function makeSchema($columns = [])
    {

        $fieldsToSkip = ['id', 'created_at', 'updated_at', 'email_verified_at', 'remember_token'];

        if (empty($columns)) {
            return;
        }

        $this->info("Found class columns!");

        $schema = [
            'items' => []
        ];

        foreach ($columns as $column) {
            if (!in_array($column['name'], $fieldsToSkip)) {
                $type = explode('(', $column['type']);
                $max = \array_key_exists(1, $type) ? $type[1] : null;
                $schema['items'][] = [
                    'name' => $column['name'],
                    'type' => $this->getType($type[0], $column['name']),
                    'validationRules' => $this->getValidation($column['name'], $max, $column['null']),
                    'label' => Str::title(str_replace('_', ' ', $column['name'])),
                    'placeholder' => $this->getPlaceholder($column['name'], $column['type']),
                ];
            }
        }

        return $schema;
    }

    public function getType($type, $name)
    {
        if (strpos($name, 'image') || strpos($name, 'logo')) {
            return 'File';
        }
        if ($name == 'password' || $name == "Password") {
            return 'Password';
        }
        if ($name == 'email' || $name == "Email" || $name == 'email_address') {
            return 'Email';
        }
        if ($type == 'varchar') {
            return 'Text';
        }
        if ($type == 'timestamp' || $type == 'date') {
            return 'Date';
        }
        if ($type == 'text') {
            return 'Textarea';
        }
        if ($type == 'boolean') {
            return 'Checkbox';
        }
    }

    public function getValidation($name, $max, $nullable)
    {
        $validationString = '';
        if ($nullable == 'NO') {
            $validationString = $this->addRuleToString($validationString, "required");
        }
        if ($name == 'email' || $name == "Email" || $name == 'email_address') {
            $validationString = $this->addRuleToString($validationString, "email");
        }
        if (isset($max)) {
            $max = \explode(')', $max);
            $validationString = $this->addRuleToString($validationString, "max:$max[0]");
        }

        return $validationString;
    }

    public function addRuleToString($string, $rule)
    {
        if (empty($string)) {
            $string = $rule;
        } else {
            $string .= "|$rule";
        }
        return $string;
    }
    public function var_export_short($data, $return = true)
    {
        $dump = var_export($data, true);

        $dump = preg_replace('#(?:\A|\n)([ ]*)array \(#i', '[', $dump); // Starts
        $dump = preg_replace('#\n([ ]*)\),#', "\n$1],", $dump); // Ends
        $dump = preg_replace('#=> \[\n\s+\],\n#', "=> [],\n", $dump); // Empties

        if (gettype($data) == 'object') { // Deal with object states
            $dump = str_replace('__set_state(array(', '__set_state([', $dump);
            $dump = preg_replace('#\)\)$#', "])", $dump);
        } else {
            $dump = preg_replace('#\)$#', "]", $dump);
        }

        if ($return === true) {
            return $dump;
        } else {
            echo $dump;
        }
    }

    public function getPlaceholder($name, $type)
    {
        if (strpos($name, 'image') || strpos($name, 'logo')) {
            return 'Upload ' . Str::title(str_replace('_', ' ', $name));
        }
        if ($type == 'boolean') {
            return 'Is this a  ' . Str::title(str_replace('_', ' ', $name)) . "?";
        }
        return 'Enter ' . Str::title(str_replace('_', ' ', $name));
    }
}
