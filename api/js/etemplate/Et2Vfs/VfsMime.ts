/**
 * EGroupware eTemplate2 - shared VFS mime-type constants
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */

/**
 * Mime-type the server reports for a directory (PHP-side Api\Vfs::DIR_MIME_TYPE).
 *
 * Own module because both Et2VfsMime and Et2VfsPath need it and neither should have to import the
 * other for a string constant.
 */
export const DIR_MIME_TYPE : string = 'httpd/unix-directory';
