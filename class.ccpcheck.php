<?php
/**
 * This is a simple class that simulates the work of KZPMailClearing.exe
 * 
 * @version 1.0
 * @author  Petar Marinov <eng.petar.marinov@gmail.com> * 
 */

class KZPMailClearing
{
	//ULR from www.kzp.bg with md5 hashes of emails and domains
	private $_email_hashes_url = 'http://www.kzp.bg/download.php?mode=fileDownload&p_attached_file_id=4956';
	//hashesh from KZP
	private $_array_hashes = array();
	//array with emails to be checked
	private $_array_emails = array();
	//emails that are not correct
	private $_incorrect_emails = array();
	//emails that are found in the kzp hashes
	private $_invalid_emails_kzp = array();
	//emails that are with invalid domains
	private $_invalid_domain_emails_kzp = array();
	//domains that are found in the hash of invalid domains
	private $_invalid_domains_kzp = array();
	//domain that are already checked
	private $_valid_domains = array();
	//emails that are cleared
	private $_valid_emails = array();
	//Extracted filename
	private $_filename;
	
	/**
	 * 
	 * @param array $emails_for_check Array of emails to be checked
	 * @param string $kzp_hash_url	
	 */
	public function __construct($kzp_hash_url = null){
		if(isset($kzp_hash_url))
			$this->_email_hashes_url = $kzp_hash_url;
		$this->_get_email_hashes_from_url();
	}
	
	/**
	 * Init the KZP check
	 * @param array $emails_for_check An array with emails to be checked
	 * @throws Exception Throws an exception if the $emails_for_check array is empty
	 * @return null
	 */
	public function check($emails_for_check = array()){
		if(!empty($emails_for_check)){
			$this->_array_emails = $emails_for_check;
		}
		else{
			throw new Exception('No emails for check passed to to the init process');
		}
		$this->_prepare_emails_for_check();
		//loop all emails
		foreach($this->_array_emails as $key=>$email){
			$domain = substr($email,strpos($email,'@')+1);
			//check if the domain is marked as invalid
			if(in_array($domain,$this->_invalid_domains_kzp)){
				$this->_invalid_domain_emails_kzp[$key] = $email;
				continue;
			}
			//check if the domain is already marked as valid
			if(!in_array($domain,$this->_valid_domains)){
				//check if the domain is valid in the hashes
				$domain_hash=md5($domain);
				if(in_array($domain_hash,$this->_array_hashes)){
					$this->_invalid_domain_emails_kzp[$key] = $email;
					$this->_invalid_domains_kzp[] = $domain;
					continue;
				}
				//mark the domain as valid
				else{
					$this->_valid_domains[] = $domain;
				}
				unset($domain_hash);
			}
			//check if the email is valid
			$email_hash = md5($email);
			if(in_array($email_hash,$this->_array_hashes)){
				$this->_invalid_emails_kzp[$key] = $email;
				continue;
			}
			unset($email_hash);
			$this->_valid_emails[$key] = $email;
		}
		unset($key);
		unset($email);
	}
	
	/**
	 * Generates report from the KZPMailClearing
	 * @return string
	 */
	public function report(){
		$dublicated_emails = count($this->_dublicated_emails);
		$incorrect_emails = count($this->_incorrect_emails);
		$invalid_emails_kzp = count($this->_invalid_emails_kzp);
		$invalid_domain_emails_kzp = count($this->_invalid_domain_emails_kzp);
		$total = $invalid_emails_kzp + $invalid_domain_emails_kzp;
		$report = 'Filename: ' . $this->_filename . "\n";
		$report .= 'Dublicated emails: ' .$dublicated_emails . "\n";
		$report .= 'Incorrect emails: ' . $incorrect_emails . "\n";
		$report .= 'Emails matched: ' . $invalid_emails_kzp . "\n";
		$report .= 'Emails matched by domain: ' . $invalid_domain_emails_kzp . "\n\n";
		$report .= 'Total found in KZP hashes: ' . $total . "\n";
		$report .= 'Emails cleared and valid: '.count($this->_valid_emails);
		return $report;
	}
	
	private function _get_email_hashes_from_url(){
		if(!$this->_email_hashes_url)
			throw new Exception('Email hashes ULR is not set');
		// create curl resource 
        $ch = curl_init(); 
        // set url
        curl_setopt($ch, CURLOPT_URL, $this->_email_hashes_url);
		//return header
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		//register a callback function which will process the headers
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array(&$this,'_read_header'));
        // $output contains the output string
        $hashes = curl_exec($ch);
        // close curl resource to free up system resources 
        curl_close($ch);
		
		$this->_array_hashes = explode("\n", $hashes);
		foreach ($this->_array_hashes as &$hash){
			$hash = strtolower(trim($hash));
		}
		unset($hash);
	}
	
	/**
	 * 
	 * @param type $ch
	 * @param type $header
	 * @return type
	 */
	private function _read_header($ch, $header) {
        //extracting example data: filename from header field Content-Disposition
        $filename = $this->_extract_custom_header('Content-Disposition: attachment; filename="', '"\s?\n', $header);
		//Content-Disposition:attachment; filename="07012013.txt"
        if ($filename) {
            $this->_filename = trim($filename);
        }
        return strlen($header);
    }

    private function _extract_custom_header($start,$end,$header) {
        $pattern = '/'. $start .'(.*?)'. $end .'/';
        if (preg_match($pattern, $header, $result)) {
            return $result[1];
        } else {
            return false;
        }
    }
	
	private function _prepare_emails_for_check(){
		if(!empty($this->_array_emails)){
			foreach($this->_array_emails as $key => &$email){
				$email = mb_strtolower(trim($email),'utf-8');
				//check if the email is valid
				if(!$this->_check_email($email)){
					$this->_incorrect_emails[$key] = $email;
					unset($this->_array_emails[$key]);
				}
			}
			unset($key);
			unset($email);
			//get duplicates
			$this->_dublicated_emails = $this->_get_duplicates($this->_array_emails);
			//unique
			$this->_array_emails = array_unique($this->_array_emails);
		}
	}
	
	/**
	 * filter_var wrapper for email ckeck
	 * @param string $email
	 * @return mixed Returns the filtered data, or FALSE if the filter fails.
	 */
	private function _check_email($email){
		return filter_var($email, FILTER_VALIDATE_EMAIL);
	}
	
	/**
	 * Takes an array and returns an array of duplicate items
	 * @param array $array
	 * @return array
	 */
	private function _get_duplicates($array) {
		return array_unique( array_diff_assoc($array, array_unique($array)));
	}
	
	/**
	 * Getter - incorrect emails
	 * @return array
	 */
	public function get_incorrect(){
		return $this->_incorrect_emails;
	}
	
	/**
	 * Getter - matched emails
	 * @return array
	 */
	public function get_matched_emails(){
		return $this->_invalid_emails_kzp;
	}
	
	/**
	 * Getter - matched emails
	 * @return array
	 */
	public function get_matched_emails_by_domain(){
		return $this->_invalid_domain_emails_kzp;
	}
	
	/**
	 * Getter - matched domains
	 * @return array
	 */
	public function get_matched_domains(){
		return $this->_invalid_domains_kzp;
	}
	
	/**
	 * Getter - cleared emails
	 * @return array
	 */
	public function get_cleared_emails(){
		return $this->_valid_emails;
	}
	
	/**
	 * Gets the name of the file that is downloaded
	 * @return array
	 */
	public function get_filename_with_hashes(){
		return $this->_filename;
	}

}
