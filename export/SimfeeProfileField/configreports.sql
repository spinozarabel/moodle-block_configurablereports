# ver 1.1 view doclinks of students in this class
#
SELECT
	CONCAT('<a target="_new" href="%%WWWROOT%%/user/view.php?id=',u.id,'">',u.username,'</a>') AS 'username',
	CONCAT(u.firstname , ' ' , u.middlename, ' ' , u.lastname) AS 'fullname',
	u.id AS 'id',
	u.idnumber AS 'idnumber',
	(select i.data
		from prefix_user_info_data i
		join prefix_user_info_field f on i.fieldid = f.id
		WHERE i.userid = u.id
		AND f.shortname = 'fees') AS 'json_fees',
	# the options control the report. Select 1 to enable and 0 to disable and save changes
	'{"select_all_rows":"0","ignore_json_parsing":"0"}' AS 'options'

FROM prefix_course AS c
JOIN prefix_context AS ctx ON c.id = ctx.instanceid
JOIN prefix_role_assignments AS ra ON ra.contextid = ctx.id
JOIN prefix_user AS u ON u.id = ra.userid

WHERE  	c.id = %%COURSEID%%
ORDER BY fullname
