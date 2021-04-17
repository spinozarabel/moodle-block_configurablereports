# ver 1.1 view doclinks of students in this class
#
SELECT u.username AS 'username',
CONCAT(u.firstname , ' ' , u.middlename, ' ' , u.lastname) AS 'fullname',
u.id AS 'id',
u.idnumber AS 'idnumber',
(select i.data
    from prefix_user_info_data i
    join prefix_user_info_field f on i.fieldid = f.id
    WHERE i.userid = u.id
    AND f.shortname = 'documentlinks') AS 'json_documentlinks'

FROM prefix_course AS c
JOIN prefix_context AS ctx ON c.id = ctx.instanceid
JOIN prefix_role_assignments AS ra ON ra.contextid = ctx.id
JOIN prefix_user AS u ON u.id = ra.userid

WHERE  	c.id = %%COURSEID%%
ORDER BY fullname
