class AnimatedSpinner {
  constructor(lines = ['Sie werden weitergeleitet ...']) {
    this.lines = lines;
    this.wrapper = null;
  }

  createSpinner() {
    const wrapper = document.createElement('div');
    wrapper.className = 'spinner-wrapper';

    const content = document.createElement('div');
    content.className = 'spinner-content';

    const circle = document.createElement('div');
    circle.className = 'spinner-circle';

    const line = document.createElement('div');
    line.className = 'spinner-line';
    line.textContent = this.lines[0];

    content.appendChild(circle);
    content.appendChild(line);
    wrapper.appendChild(content);

    document.body.appendChild(wrapper);

    this.wrapper = wrapper;
  }

  removeSpinner() {
    if (this.wrapper) {
      this.wrapper.remove();
    }
  }
}

// Example Usage
document.addEventListener('DOMContentLoaded', () => {
  const spinner = new AnimatedSpinner();
  spinner.createSpinner();

  // Remove the spinner after 15 seconds (adjust for debugging)
  setTimeout(() => spinner.removeSpinner(), 15000);
});
