document.addEventListener('DOMContentLoaded', function() {
    // =================================================================
    // SECTION 1: GLOBAL DOM ELEMENT SELECTION
    // =================================================================
    
    const welcomeScreen = document.getElementById('welcomeScreen');
    const newStudentFormContainer = document.getElementById('newStudentFormContainer');
    const returningStudentFormContainer = document.getElementById('returningStudentFormContainer');
    const thankYou = document.getElementById('thankYou');
    const addSiblingScreen = document.getElementById('addSiblingScreen');

    const newStudentBtn = document.getElementById('newStudentBtn');
    const returningStudentBtn = document.getElementById('returningStudentBtn');
    const homeBtn = document.getElementById('homeBtn'); 
    const addAnotherSiblingBtn = document.getElementById('addAnotherSiblingBtn');
    const finishRegistrationBtn = document.getElementById('finishRegistrationBtn');

    const returningStudentForm = document.getElementById('returningStudentForm');
    const enrollmentForm = document.getElementById('enrollmentForm');
    
    const addedStudentName = document.getElementById('addedStudentName');

    // =================================================================
    // SECTION 2: STATE MANAGEMENT
    // =================================================================
    
    let currentFamilyId = null; 

    // =================================================================
    // SECTION 3: ATTACH EVENT LISTENERS (ONLY ONCE)
    // =================================================================
    
    initializeNewStudentForm(); // ** FIX: Initialize the multi-step form logic ONCE on page load. **

    newStudentBtn.addEventListener('click', () => {
        welcomeScreen.classList.add('hidden');
        newStudentFormContainer.classList.remove('hidden');
        // We no longer call initialize here. It's already running.
    });
    
    returningStudentBtn.addEventListener('click', () => {
        welcomeScreen.classList.add('hidden');
        returningStudentFormContainer.classList.remove('hidden');
    });

    returningStudentForm.addEventListener('submit', function(event) {
        event.preventDefault();
        const submitBtn = this.querySelector('button[type="submit"]');
        handleFormSubmission(this, submitBtn, returningStudentFormContainer);
    });
    
    addAnotherSiblingBtn.addEventListener('click', () => {
        addSiblingScreen.classList.add('hidden');
        
        // Reset forms and UI for the next sibling
        returningStudentForm.reset();
        enrollmentForm.reset();

        // ** FIX: Restore submit buttons to their initial state **
        const returningSubmitBtn = returningStudentForm.querySelector('button[type="submit"]');
        returningSubmitBtn.disabled = false;
        returningSubmitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> تأكيد التسجيل';

        const newStudentNextBtn = document.getElementById('nextBtn');
        const newStudentPrevBtn = document.getElementById('prevBtn');
        newStudentNextBtn.disabled = false;
        newStudentPrevBtn.disabled = false;

        // ** FIX: Call the reset function for the multi-step form **
        resetNewStudentForm();

        welcomeScreen.classList.remove('hidden');
    });

    finishRegistrationBtn.addEventListener('click', () => {
        addSiblingScreen.classList.add('hidden');
        thankYou.classList.remove('hidden');
    });
    
    homeBtn.addEventListener('click', (e) => {
        e.preventDefault();
        window.location.reload();
    });

    // =================================================================
    // SECTION 4: NEW STUDENT MULTI-STEP FORM LOGIC
    // =================================================================
    
    // This function now only attaches listeners and defines the logic.
    function initializeNewStudentForm() {
        let currentStep = 1;
        
        const stepContents = newStudentFormContainer.querySelectorAll('.step-content');
        const stepIndicators = newStudentFormContainer.querySelectorAll('.step-indicator .step');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');

        // This function will be called to reset the form for siblings
        window.resetNewStudentForm = function() {
            currentStep = 1;
            updateUI();
        };

        prevBtn.addEventListener('click', goToPreviousStep);
        nextBtn.addEventListener('click', handleNextOrSubmit);
        enrollmentForm.addEventListener('submit', function(event) {
            event.preventDefault();
            handleFormSubmission(this, nextBtn, newStudentFormContainer, prevBtn);
        });

        function handleNextOrSubmit() {
            if (!validateCurrentStep()) return; 

            // ** FIX: Skip step 3 if we are adding a sibling **
            if (currentStep === 2 && currentFamilyId) {
                currentStep = 4; // Jump from step 2 directly to step 4
            } else {
                currentStep++;
            }
            
            const totalSteps = currentFamilyId ? 4 : 5; // Total steps depends on if it's a sibling
            if (currentStep > totalSteps) {
                enrollmentForm.requestSubmit();
            } else {
                updateUI();
            }
        }
    
        function goToPreviousStep() {
            if (currentStep === 1) return;

            // ** FIX: Skip step 3 if we are adding a sibling **
            if (currentStep === 4 && currentFamilyId) {
                currentStep = 2; // Jump from step 4 directly back to step 2
            } else {
                currentStep--;
            }
            updateUI();
        }
    
        function updateUI() {
            // ** FEATURE: Hide parent info step for siblings **
            const parentStepContent = newStudentFormContainer.querySelector('.step-content[data-step="3"]');
            const parentStepIndicator = newStudentFormContainer.querySelector('.step-indicator .step[data-step="3"]');
            if (currentFamilyId) {
                parentStepContent.classList.add('hidden');
                parentStepIndicator.classList.add('hidden');
            } else {
                parentStepContent.classList.remove('hidden');
                parentStepIndicator.classList.remove('hidden');
            }
            
            stepContents.forEach(c => c.classList.toggle('active', parseInt(c.dataset.step) === currentStep));
            stepIndicators.forEach(i => {
                const step = parseInt(i.dataset.step);
                i.classList.remove('active', 'completed');
                if (step === currentStep) i.classList.add('active');
                else if (step < currentStep) i.classList.add('completed');
            });
            prevBtn.style.display = currentStep === 1 ? 'none' : 'inline-block';
            
            const totalSteps = currentFamilyId ? 4 : 5;
            nextBtn.innerHTML = currentStep === totalSteps ? '<i class="fas fa-paper-plane"></i> Submit - إرسال' : 'Next - التالي <i class="fas fa-arrow-right"></i>';
        }

        function validateCurrentStep() {
            let isValid = true;
            const currentStepContent = newStudentFormContainer.querySelector(`.step-content[data-step="${currentStep}"]`);
            const requiredFields = currentStepContent.querySelectorAll('[required]');
            for (const field of requiredFields) {
                if (!field.value.trim()) {
                    isValid = false; field.style.borderColor = 'var(--danger)';
                    setTimeout(() => { field.style.borderColor = ''; }, 3000);
                    break; 
                }
            }
            if (!isValid) alert('Please fill all required fields marked with *');
            return isValid;
        }
    }

    // =================================================================
    // SECTION 5: UNIVERSAL FORM SUBMISSION HANDLER
    // =================================================================

    function handleFormSubmission(formElement, submitBtn, formContainer, prevBtn = null) {
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        if (prevBtn) prevBtn.disabled = true;

        const formData = new FormData(formElement);
        if (currentFamilyId) {
            formData.append('family_id', currentFamilyId);
        }

        fetch('process.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    currentFamilyId = data.family_id;
                    addedStudentName.textContent = data.student_name;
                    formContainer.classList.add('hidden');
                    addSiblingScreen.classList.remove('hidden');
                    window.scrollTo(0, 0);
                } else {
                    alert('Submission failed: ' + (data.message || 'Unknown server error.'));
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                    if (prevBtn) prevBtn.disabled = false;
                }
            } catch (error) {
                console.error("Server Response Error:", text);
                alert("An error occurred on the server.");
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                if (prevBtn) prevBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            alert('A network error occurred.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            if (prevBtn) prevBtn.disabled = false;
        });
    }
});