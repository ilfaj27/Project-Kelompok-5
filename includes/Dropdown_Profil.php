 <? 
  // ── Dropdown Profil Toggle ─────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', () => {
            const profileTrigger = document.getElementById('profileTrigger');
            const profileDropdownMenu = document.getElementById('profileDropdownMenu');
            const dropdownContainer = document.querySelector('.profile-dropdown-container');

            if (profileTrigger && profileDropdownMenu) {
                profileTrigger.addEventListener('click', (e) => {
                    e.stopPropagation();
                    profileDropdownMenu.classList.toggle('show');
                    dropdownContainer.classList.toggle('active');
                });

                document.addEventListener('click', (e) => {
                    if (!dropdownContainer.contains(e.target)) {
                        profileDropdownMenu.classList.remove('show');
                        dropdownContainer.classList.remove('active');
                    }
                });
            }
        });

?>        