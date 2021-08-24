<?php
/* by Madhu Avasarala 13/08/2021
* ver 1.0
*/

namespace block_configurable_reports\madhu_export_classes;

// if directly called die. Use standard WP and Moodle practices
defined('MOODLE_INTERNAL') || die('direct access to this file is not permitted');

// class definition begins
class cashfree_sync
{
    public $vAupdate_user_profile =   true;       // flag to update user profile filed or not, with possible new data

    public function __construct( $report, $desired_site_name, $verbose = true )
    {
        $this->verbose = $verbose;

        $this->report = $report;

        $this->desired_site_name = $desired_site_name;  // example: hset-payyments

        // read in configuration settings and set them as properties to this
        $this->get_config();

        // matrix is record rows as an array. It is not yet associative
        $matrix  = $this->get_report_matrix();

        // get working copy before manipulation
        $matrix_associative = $matrix;

        // transform copy into associative
        array_walk($matrix_associative, function(&$a) use ($matrix_associative)
		{
			$a = array_combine($matrix_associative[0], $a);
		});
	    array_shift($matrix_associative); # remove column header

        // write back associative matrix as property to this object
        $this->matrix_associative = $matrix_associative;
    }

    private function get_config()
    {
        
        $this->vAupdate_user_profile =   $vAupdate_user_profile;

        // read in comma separated list of site names from config_settings of plugin
        $site_names_config  = get_config('block_configurable_reports', 'site_names') ?? "";

        // if empty exit with error message
        if (empty($site_names_config))
        {
            echo nl2br("Empty config setting for site names, please set in config: "  . "\n");
            return;
        }

        // read in the comma separated site names into an array.
        $this->$site_names_arr     = explode( "," , $site_names_config ) ?? [];

        $num_sites          = count($this->site_names_arr) ?? 0;

        // return if num_sites is 0
        if (0 == $num_sites)
        {
            echo nl2br("number of sites evaluates to 0, please set correctly in config settings site names: "  . "\n");
            return;
        }

        // read in beneficiary names from config settings into an array
        $account_names_config = get_config('block_configurable_reports', 'account_names') ?? "";

        // if empty exit with error message
        if (empty($account_names_config))
        {
            echo nl2br("Empty config setting for account names, please set in config: "  . "\n");
            return;
        }

        // read in comma separated account names into an array
        $this->$account_nammes_arr = explode( "," , $account_names_config );
    }


    public function get_report_matrix ()
    {
        $table      = $this->report->table;
        $matrix     = array();
        $filename   = 'report';
        $accounts   = array();

        if (!empty($table->head))
        {
            $countcols = count($table->head);
            $keys      = array_keys($table->head);
            $lastkey   = end($keys);

            foreach ($table->head as $key => $heading)
            {
                $matrix[0][$key] = str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($heading))));
            }
        }

        if (!empty($table->data))
        {
            foreach ($table->data as $rkey => $row)
            {
                foreach ($row as $key => $item)
                {
                    $matrix[$rkey + 1][$key] = str_replace("\n", ' ', htmlspecialchars_decode(strip_tags(nl2br($item))));
                }
            }
        }

        return $matrix;
    }


    /**
     * This is the account in json format for desired site
     */
    public function get_desired_site_account()
    {
        $desired_site_name = $this->desired_site_name;

        
    }


    /**
     * 
     */
    public function new_fees($simulation = true)
    {
        // reread the config file incase there have been recent changes
        $this->get_config();

        // print the table header
        $this->print_fee_table_header();

        foreach ($this->matrix_associative as $key => $user):
            // for thiss user look up fees from sheet and formulate the new fees array to be added
            $new_fees_arr = $this->get_new_fees_array( $user, $this->fees_csv );

            // echo nl2br("New fees Array looked up in fees_csv array");
            // echo "<pre>" . print_r($new_fees_arr, true) ."</pre>";

            // read in the existing fees array from this user's custom field
            $updated_fees_arr = $this->insert_new_fees_and_update_profile_field( $user, $new_fees_arr, $simulation );

            // print out a row of the fee table for this user's fee
            $this->print_fee_table_row( $user, $updated_fees_arr, $new_fees_arr );

        endforeach;

        $this->print_footer();
    }


    /**
     * 
     */
    public function xxx()
    {

    }




    /**
     * This routine is attributed to https://github.com/rap2hpoutre/csv-to-associative-array
     *
    * The items in the 1st line (column headers) become the fields of the array
    * each line of the CSV file is parsed into a sub-array using these fields
    * The 1st index of the array is an integer pointing to these sub arrays
    * The 1st row of the CSV file is ignored and index 0 points to 2nd line of CSV file
    * This is the example data:
    *
    * grade1,grade2,grade3
    * grade2,grade3,grade4
    * 10000,20000,30000
    *
    * This is the associative array
    * Array
    *(
    *  [0] => Array
    *		(
    *		   [grade1] => grade2
    *          [grade2] => grade3
    *		   [grade3] => grade4
    *		)
    *  [1] => Array
    *      (
    *          [grade1] => 10000
    *          [grade2] => 20000
    *          [grade3] => 30000
    *      )
    * )
    */
    public function  csvfile_to_associative_array ( $file, $delimiter = ',', $enclosure = '"' )
    {
        if (($handle = fopen($file, "r")) !== false)
        {
            $headers = fgetcsv($handle, 0, $delimiter, $enclosure);
            $lines = [];
            while (($data = fgetcsv($handle, 0, $delimiter, $enclosure)) !== false)
            {
                $current = [];
                $i = 0;
                foreach ($headers as $header)
                {
                    $current[$header] = $data[$i++];
                }
                $lines[] = $current;
            }
            fclose($handle);
            return $lines;
        }
    }
}