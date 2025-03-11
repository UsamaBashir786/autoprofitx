// Invalid
function doSomething() {}
import { something } from './module'; // Error: Declaration or statement expected

// Valid
import { something } from './module';
function doSomething() {}
