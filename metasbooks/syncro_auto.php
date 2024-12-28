<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

class Syncro_Auto
{
    public static function generate()
    {
        global $wpdb;
        $metasbooks_table = $wpdb->prefix . 'metasbooks';
        $sync_enabled = $wpdb->get_var("SELECT sync FROM $metasbooks_table WHERE id = 1");

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manage_cron_submit'])) {
            $enable_sync = isset($_POST['manage_cron']) && $_POST['manage_cron'] === 'enable';

            if ($enable_sync && !$sync_enabled) {
                $wpdb->update($metasbooks_table, ['sync' => 1], ['id' => 1]);
                self::schedule_sync_event();
            } elseif (!$enable_sync && $sync_enabled) {
                $wpdb->update($metasbooks_table, ['sync' => 0], ['id' => 1]);
                self::unschedule_sync_event();
            }

            wp_redirect($_SERVER['REQUEST_URI']);
            exit;
        }

        $checked = $sync_enabled ? 'checked' : '';

        echo "<h1>MetasBooks</h1>";
        echo "<h3>Configuration de la synchronisation automatique des stocks :</h3>";
        echo "<form method='post' action=''>";
        echo "<label for='manage_cron'>Activer la synchronisation automatique :</label> ";
        echo "<input type='checkbox' name='manage_cron' value='enable' $checked> ";
        echo $sync_enabled ? "La synchronisation automatique est activée &#10003;<br><br>" : "La synchronisation est désactivée.<br><br>";
        echo "<input type='submit' name='manage_cron_submit' value='Enregistrer les modifications'>";
        echo "</form>";

        echo "<br>La synchronisation s'effectue tous les jours à 3h, uniquement si votre compte MetasBooks est correctement configuré.";
        echo "<br>Consultez notre <a href='https://metasbooks.fr/tutoriel_envois_stock.htm/'>tutoriel</a> pour la configuration de l'envoi automatique de fichiers XML.";
    }

    private static function schedule_sync_event()
    {
        $target_hour = 3;
        $current_time = current_time('timestamp');
        $target_time = strtotime(date('Y-m-d', $current_time) . " $target_hour:00:00");

        if ($current_time > $target_time) {
            $target_time = strtotime('+1 day', $target_time);
        }

        if (!wp_next_scheduled('metasbooks_sync')) {
            wp_schedule_event($target_time, 'daily', 'metasbooks_sync');
        }
    }

    private static function unschedule_sync_event()
    {
        $timestamp = wp_next_scheduled('metasbooks_sync');

        if ($timestamp) {
            wp_unschedule_event($timestamp, 'metasbooks_sync');
        }
    }
}

function ajouterLog($message)
{
    $cheminFichierLog = plugin_dir_path(__FILE__) . 'logs.txt';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($cheminFichierLog, $logMessage, FILE_APPEND);
}

add_action('metasbooks_sync', function () {
    set_time_limit(0);

    $cheminFichierXml = plugin_dir_path(__FILE__) . 'stock/stock.xml';

    if (!file_exists($cheminFichierXml)) {
        ajouterLog("Le fichier de stock est introuvable &#10060;");
        return;
    }

    $reader = new XMLReader();

    if (!$reader->open($cheminFichierXml)) {
        ajouterLog("Impossible d'ouvrir le fichier XML &#10060;");
        return;
    }

    $isValid = false;
    $datas = ['case' => ''];

    while ($reader->read()) {
        if ($reader->nodeType == XMLReader::ELEMENT && $reader->localName == 'record') {
            $node = new SimpleXMLElement($reader->readOuterXML());
            $ean = isset($node->ean) ? (string)$node->ean : null;
            $stock = isset($node->stock) ? (string)$node->stock : null;

            if ($ean && $stock) {
                require_once plugin_dir_path(__FILE__) . 'handler.php';

                if (!$isValid) {
                    ajouterLog("Fichier XML correctement formaté &#10003;");
                    handler::define(['case' => 'reset_stocks']);
                    $isValid = true;
                }

                handler::define(['case' => 'update_create', 'ean' => $ean, 'stock' => $stock]);
            }
        }
    }

    $reader->close();

    if (!$isValid) {
        ajouterLog("Le fichier XML semble mal formaté &#10060;");
    }
});

if (isset($_REQUEST['runsync'])) {
    global $wpdb;
    $password = sanitize_text_field($_REQUEST['pswd'] ?? '');
    $metasbooks_table = $wpdb->prefix . 'metasbooks';
    $sql = "SELECT sync_password FROM $metasbooks_table WHERE id = 1";
    $stored_password = $wpdb->get_var($sql);

    if (hash_equals($stored_password, $password)) {
        do_action('metasbooks_sync');
    } else {
        wp_die("Mot de passe invalide &#10060;");
    }
}
