<?php

declare(strict_types=1);

namespace Framework\Utilities\FileSystem;

use \Framework\Utilities\UtilitiesFramework as UtilitiesFramework;

/**
 * This class provides functions for managing files
 *
 * @category   UtilityClass
 * @author     Nadir Latif <nadir@pakjiddat.pk>
 * @license    https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2
 */
final class FileManager
{
    /** @var string $upload_folder Location of upload folder */
    private $upload_folder;
    /** @var string $max_allowed_file_size Maximum size of file that can be uploaded */
    private $max_allowed_file_size;
    /** @var array $allowed_extensions Contains extensions for each file type that is permitted for uploading */
    private $allowed_extensions;
    /** @var FileManager $instance The single static instance */
    protected static $instance;
    
    /**
     * Used to return a single instance of the class
     *
     * Checks if instance already exists. If it does not exist then it is created. The instance is returned
     *
     * @return FileManager static::$instance name the instance of the correct child class is returned
     */
    public static function GetInstance($parameters) : FileManager
    {
        if (static ::$instance == null) {
            static ::$instance = new static ($parameters);
        }
        return static ::$instance;
    }
    /**
     * Class constructor
     *
     * @param array $parameters config information for the class
     *    upload_folder => string the location of the upload_folder
     *    allowed_extensions => array the file types that are allowed to be uploaded
     *    max_allowed_file_size => string the maximum allowed size for uploaded files
     */
    public function __construct(array $parameters)
    {
        $this->upload_folder         = $parameters['upload_folder'] ?? '';
        $this->allowed_extensions    = $parameters['allowed_extensions'] ?? '';
        $this->max_allowed_file_size = $parameters['max_allowed_file_size'] ?? '';
    }
    
	/**
     * Used to download and parse the given file
     *
     * It downloads the file from the given url
     * It then converts the file contents into an array
     * Each line is parsed and the field values are extracted
     * CSV and TXT files are supported
     * Field names in csv files must be enclosed with "" and separated with ,
     * The field values are parsed by the custom callback function
     *
     * @param string $file_url the url of the file
     * @param string $local_file_path the path where the file should be downloaded
     * @param callable the callback function for processing the file contents
     *
     * @return array $data the contents of the file. each array element contains the data for a line
     */
    public function DownloadAndParseFile(string $file_url, string $local_file_path, callable $line_parsing_callback) : array
    {
        /** The parsed contents of the file */
        $data                                       = array();
        /** The file data containing file name and extension */
        $file_details                               = UtilitiesFramework::Factory("stringutils")->GetFileNameAndExtension($file_url);
        /** The file name */
        $file_name                                  = $file_details['file_name'];
        /** The file name is url decoded */
        $file_name                                  = urldecode($file_name);
        /** The file extension */
        $file_extension                             = $file_details['file_extension'];
        /** The absolute path to the downloaded file */
        $file_path                                  = $local_file_path . DIRECTORY_SEPARATOR . $file_name;
        /** If the file exists locally then it is read */
        if (is_file($file_path)) {
            $file_contents                          = $this->ReadLocalFile($file_path);
        }
        /** Otherwise the file contents are fetched and saved to local file */
        else {
            $file_contents                          = $this->GetFileContent($file_url);
            /** The file contents are saved locally */
            $this->WriteLocalFile($file_contents, $file_path);
        }
        /** The file contents are converted to array */
        $file_contents                              = explode("\n", $file_contents);
        /** The components of each meta data item is extracted using regular expression */
        for ($count = 0; $count < count($file_contents); $count++) {
            /** The line string */
            $line                                   = trim($file_contents[$count]);
            /** If the line is empty then it is ignored */
            if ($line == '') break;
            /** The parametes for the callback function */
            $parameters[0]                          = $file_extension;
            $parameters[1]                          = $line;
            /** The parsed lined. It is generated by the line parsing callback function */
            $parsed_line                            = call_user_func_array($line_parsing_callback, $parameters);
            /** If the line was successfully parsed then it is appended to the data */
            if ($parsed_line) $data[]               = $parsed_line;
        }
        return $data;
    }
	/**
     * Deletes the given file from local disk
     *
     * @param string $file_path the absolute path to the file
     *
     * @throws Exception throws an exception if the file could not be deleted
     */
    public function DeleteLocalFile(string $file_path) : void
    {
        /** If the file cannot be removed, then an exception is thrown */
        if (!unlink($file_path)) throw new \Error("File could not be deleted");
    }
    /**
     * Writes the given text to a file on local disk
     *
     * @param string $file_text the text that needs to be written to local file
     * @param string $file_path the absolute path to the file
     * @param string $file_mode [a~w] the mode in which the file should be opened
     *
     * @throws Exception throws an exception if the file could not be written
     */
    public function WriteLocalFile(string $file_text, string $file_path, string $file_mode = "w") : void
    {
        /** The file is opened */
        $fh = fopen($file_path, $file_mode);
        /** If the file cannot be written, then an exception is thrown */
        if (!fwrite($fh, $file_text)) throw new \Error("Text could not be written to the file: " . $file_path);
        /** If the file was written, then it is closed */
        else fclose($fh);
    }
    /**
     * Reads the contents of a file on disk
     *
     * @param string $file_path absolute path to the file to be read
     *
     * @return string returns the contents of the file
     */
    public function ReadLocalFile(string $file_path) : string
    {
    	/** If the file size is 0 then an empty string is returned */
    	if (filesize($file_path) == '0') $contents = "";
    	/** If the file size is not 0  */
    	else {
			/** The file is opened */
		    $fh                                    = fopen($file_path, "r");
		    /** The file contents are read */
		    $contents                              = fread($fh, filesize($file_path));
		    /** The file is closed */
		    fclose($fh);
        }
        
        return $contents;
    }
    /**
     * Copies the source file to the target file
     *
     * It overwrites the destination file
     *
     * @param string $source_file the source file to copy
     * @param string $target_file the target file name
     *
     * @throws \Error throws an exception if the file could not be copied
     */
    public function CopyFile(string $source_file, string $target_file) : void 
    {
        /** If the source file could not be copied to the target file */
        if (!copy($source_file, $target_file)) {
            throw new \Error(
                          "Source file: " . $source_file . " could not be copied to target file: " . $target_file
                      );
        }                     
    }
    /**
     * Copies as uploaded file to a given location. the location is set in the private class variable
     *
     * @param array $file_data data for uploaded file
     *
     * @throws \Error throws an exception if the file size is greater than a limit
     *   or the file extension is not valid or the uploaded file could not be copied.
     *   The upload limit and valid file extensions are specifed in private class variables
     *
     * @return string $path full path to the uploaded file.
     */
    public function UploadFile(array $file_data) : string
    {
        /** If the name of uploaded file is not set, then exception is thrown */
        if (!isset($file_data["name"])) 
            throw new \Error("No file to upload");
            
        /** The maximum allows file size */
        $max_allowed_file_size = $this->max_allowed_file_size;
        /** The list of allowed extensions */
        $allowed_extensions    = $this->allowed_extensions;
        /** The size of the uploaded file */
        $file_size             = ceil($file_data['size'] / 1024);
        /** The file name */
        $file_name             = $file_data["name"];
        /** The extension of the uploaded file */
        $file_ext              = substr($file_name, strrpos($file_name, ".") + 1);
        if ($file_size > $max_allowed_file_size) 
            throw new \Error("Size of file should be less than " . $max_allowed_file_size . " Kb");
        
        /** Indicates if the file extension is valid */
        $allowed_ext = false;
        /** Each allowed file extension is checked */
        for ($i = 0;$i < sizeof($allowed_extensions);$i++) {
            /** If the file extension matches */
            if (strcasecmp($allowed_extensions[$i], $file_ext) == 0) {
                /** The file extension is marked as valid */
                $allowed_ext   = true;
                /** The loop ends */
                break;
            }
        }
        /** If the file extension is not allowed, then exception is thrown */
        if (!$allowed_ext) 
            /** The error message */
            $message           = "The uploaded file is not a supported file type.";
            $message          .= "Only the following file types are supported: " . implode(',', $allowed_extensions);
            /** The Exception is thrown */
            throw new \Error($message);
       
        /** The path to the file after it has been moved to the upload folder */
        $path                  = $this->upload_folder . DIRECTORY_SEPARATOR . $file_name;
        /** The path to the temprary file */
        $tmp_path              = $file_data["tmp_name"];
        
        /** If the file was uploaded */
        if (is_uploaded_file($tmp_path)) {
            /** If the temprary file path and file path do not match and the file could not be copied */
            if ($tmp_path != $path && !copy($tmp_path, $path)) {
                /** The error message */
                $message       = "Error while copying the uploaded file from: " . $tmp_path . " to " . $path;
                /** The exception is thrown */
                throw new \Error($message);
            }
        }
        return $path;
    }
    /**
     * Searches for the given file     
     *
     * It searches the given folder for the given file name
     *
     * @param array $search_folders the list of folder paths to search
     * @param string $file_name the name of the file to search
     *
     * @return string $template_file_path the absolute path to the template file
     */
    public function SearchFile(array $search_folders, string $file_name) : string
    {
    	/** The file name is prefixed with '/' */
    	$file_name                 = DIRECTORY_SEPARATOR . ltrim($file_name, DIRECTORY_SEPARATOR);
        /** The required template file path */
        $template_file_path        = "";
        /** Each folder is searched */
        for ($count1 = 0; $count1 < count($search_folders); $count1++) {
            /** The folder path to search */
            $folder_path           = $search_folders[$count1];
            /** The folder is searched */
            $folder_contents       = UtilitiesFramework::Factory("foldermanager")->GetFolderContents(
                                         $folder_path,
                                         -1,
                                         "",
                                         "",
                                         true
                                     );
            /** Each folder is searched */
            for ($count2 = 0; $count2 < count($folder_contents); $count2++) {
                /** The absolute path to the folder or file */
                $item_path         = $folder_contents[$count2];
                /** If the item is a folder then the loop continues */
                if (is_dir($item_path)) continue;
                /** If the file name matches */
                if (strpos($item_path, $file_name) !== false) {
                    /** The template file path is set */
                    $template_file_path = $item_path;
                    /** The loop ends */
                    break;
                }
            }
            /** If the template file was found, then the loop ends */
            if ($template_file_path !="") break;
        }
       
        return $template_file_path;
    }
}
