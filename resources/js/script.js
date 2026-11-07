function checkEmail(emailField) {
    if (emailField.value === '') {
        $('#used').html('&nbsp;');
        return;
    }

    $.post('checkemail.php', { email: emailField.value }, function(data) {
        $('#used').html(data);
    });
}

function toggleRoleFields() {
    let role = $('#role').val();

    if (role === 'lawyer') {
        $('#lawyerFields').show();
        $('#clientFields').hide();
    } else {
        $('#lawyerFields').hide();
        $('#clientFields').show();
    }
}
