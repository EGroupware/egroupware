/**
 * EGroupware emailadmin static javascript code
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package emailadmin
 * @link http://www.egroupware.org
 * @author Klaus Leithoff <kl@stylite.de>
 * @version $Id$
 */

function disableGroupSelector()
{
	//alert('Group'+document.getElementById('exec[ea_group]').value+' User'+document.getElementById('eT_accountsel_exec_ea_user').value);
	if (document.getElementById('eT_accountsel_exec_ea_user').value != '')
	{
		if (document.getElementById('exec[ea_group]').value != '') document.getElementById('exec[ea_group]').value = '';
		document.getElementById('exec[ea_group]').disabled = true;
	}
	else
	{
		document.getElementById('exec[ea_group]').disabled = false;
	}
}
